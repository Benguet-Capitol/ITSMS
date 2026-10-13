<?php

use App\Enums\TicketStatus;
use App\Http\Controllers\AgencyController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\BrandModelController;
use App\Http\Controllers\CctvFootageRequestController;
use App\Http\Controllers\CommonProblemController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DesignRequestController;
use App\Http\Controllers\DocumentationRequestController;
use App\Http\Controllers\DtsUserRequestController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryReportController;
use App\Http\Controllers\IsChangeRequestController;
use App\Http\Controllers\ItemTypeController;
use App\Http\Controllers\ItServiceController;
use App\Http\Controllers\ItSupplyController;
use App\Http\Controllers\MeasurementUnitController;
use App\Http\Controllers\NetworkAssistanceRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\OtherItServiceReportController;
use App\Http\Controllers\OtherItServiceRequestController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SolutionController;
use App\Http\Controllers\TicketComplexityLevelController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketReportController;
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
    | Get Online Users routes
    |--------------------------------------------------------------------------
    */

    Route::get('users/online-list', [
        UserController::class,
        'onlineList',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Dashboard Summary routes
    |--------------------------------------------------------------------------
    */

    Route::get('tickets/dashboard-summary', [
        TicketController::class,
        'dashboardSummary',
    ])->middleware('can:tickets.view');

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
    Route::get('permissions/{permission}/usage', [
        PermissionController::class,
        'usage',
    ]);
    Route::apiResource('roles', RoleController::class);
    Route::get('roles/{role}/usage', [
        RoleController::class,
        'usage',
    ]);
    Route::apiResource('positions', PositionController::class);

    Route::apiResource('employees', EmployeeController::class)
        ->only(['index', 'show']);

    Route::apiResource('brands', BrandController::class);
    Route::apiResource('brand-models', BrandModelController::class);
    Route::get('brand-models/{brand_model}/usage', [
        BrandModelController::class,
        'usage',
    ]);
    Route::apiResource('item-types', ItemTypeController::class);
    Route::get('item-types/{item_type}/usage', [
        ItemTypeController::class,
        'usage',
    ]);
    Route::apiResource('common-problems', CommonProblemController::class);
    Route::apiResource('inventories', InventoryController::class);
    Route::get('inventories/{inventory}/repair-history', [
        InventoryController::class,
        'repairHistory',
    ])->middleware('can:inventories.view');
    Route::apiResource('it-services', ItServiceController::class);
    Route::apiResource('tickets', TicketController::class);
    Route::apiResource('solutions', SolutionController::class);
    Route::apiResource('agencies', AgencyController::class);
    Route::get('agencies/{agency}/usage', [
        AgencyController::class,
        'usage',
    ]);
    Route::apiResource('measurement-units', MeasurementUnitController::class);
    Route::apiResource('ticket-complexity-levels', TicketComplexityLevelController::class);
    Route::apiResource('it-supplies', ItSupplyController::class);
    Route::apiResource(
        'other-it-service-requests',
        OtherItServiceRequestController::class
    );
    Route::apiResource(
        'cctv-footage-requests',
        CctvFootageRequestController::class
    );
    Route::apiResource(
        'documentation-requests',
        DocumentationRequestController::class
    );
    Route::apiResource(
        'network-assistance-requests',
        NetworkAssistanceRequestController::class
    );
    Route::apiResource(
        'design-requests',
        DesignRequestController::class
    );
    Route::apiResource(
        'dts-user-requests',
        DtsUserRequestController::class
    );
    Route::apiResource(
        'is-change-requests',
        IsChangeRequestController::class
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

        Route::get('ticket-complexity-levels', [
            TicketComplexityLevelController::class,
            'select',
        ])->middleware('can:ticket_complexity_levels.select');

        Route::get('offices', [
            OfficeController::class,
            'index',
        ])->middleware('can:offices.view');

        Route::get('common-problems', [
            CommonProblemController::class,
            'select',
        ])->middleware('can:common_problems.select');
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

        Route::get('tickets', [
            TicketController::class,
            'search',
        ])->middleware('can:tickets.search');
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

        Route::get('word', [
            InventoryReportController::class,
            'exportWord',
        ])->middleware('can:inventories.report');
    });

    Route::prefix('tickets/reports')->group(function () {
        Route::get('technicians', [
            TicketReportController::class,
            'technicianSelect',
        ])->middleware('can:tickets.report');

        Route::get('excel', [
            TicketReportController::class,
            'exportExcel',
        ])->middleware('can:tickets.report');

        Route::get('pdf', [
            TicketReportController::class,
            'exportPdf',
        ])->middleware('can:tickets.report');

        Route::get('word', [
            TicketReportController::class,
            'exportWord',
        ])->middleware('can:tickets.report');

        Route::get('technician-performance/excel', [
            TicketReportController::class,
            'technicianPerformanceExcel',
        ])->middleware('can:tickets.report');

        Route::get('technician-performance/pdf', [
            TicketReportController::class,
            'technicianPerformancePdf',
        ])->middleware('can:tickets.report');

        Route::get('technician-performance/word', [
            TicketReportController::class,
            'technicianPerformanceWord',
        ])->middleware('can:tickets.report');
    });

    Route::prefix('other-it-service-requests/reports')->group(function () {
        Route::get('summary', [
            OtherItServiceReportController::class,
            'summary',
        ])->middleware('can:requests.other_it_services.report');

        Route::get('excel', [
            OtherItServiceReportController::class,
            'exportExcel',
        ])->middleware('can:requests.other_it_services.report');

        Route::get('pdf', [
            OtherItServiceReportController::class,
            'exportPdf',
        ])->middleware('can:requests.other_it_services.report');

        Route::get('word', [
            OtherItServiceReportController::class,
            'exportWord',
        ])->middleware('can:requests.other_it_services.report');
    });

    /*
    |--------------------------------------------------------------------------
    | Other IT service request actions
    |--------------------------------------------------------------------------
    */

    // GET, not POST: it only downloads the PDF, and the frontend (like the
    // CCTV and ticket assessment printouts) fetches it with GET -- as POST
    // every Print click failed with 405.
    Route::get(
        'other-it-service-requests/{otherItServiceRequest}/print',
        [
            OtherItServiceRequestController::class,
            'print',
        ]
    )->middleware('can:requests.other_it_services.print');

    /*
    |--------------------------------------------------------------------------
    | CCTV footage request actions
    |--------------------------------------------------------------------------
    */

    Route::get(
        'cctv-footage-requests/{cctv_footage_request}/print',
        [
            CctvFootageRequestController::class,
            'print',
        ]
    )->middleware('can:requests.cctv_footage.print');

    /*
    |--------------------------------------------------------------------------
    | Documentation request actions
    |--------------------------------------------------------------------------
    */

    Route::get(
        'documentation-requests/{documentation_request}/print',
        [
            DocumentationRequestController::class,
            'print',
        ]
    )->middleware('can:requests.documentation.print');

    /*
    |--------------------------------------------------------------------------
    | Network assistance request actions
    |--------------------------------------------------------------------------
    */

    Route::get(
        'network-assistance-requests/{network_assistance_request}/print',
        [
            NetworkAssistanceRequestController::class,
            'print',
        ]
    )->middleware('can:requests.network_assistance.print');

    /*
    |--------------------------------------------------------------------------
    | Design/lay-out/editing request actions
    |--------------------------------------------------------------------------
    */

    Route::get(
        'design-requests/{design_request}/print',
        [
            DesignRequestController::class,
            'print',
        ]
    )->middleware('can:requests.design.print');

    /*
    |--------------------------------------------------------------------------
    | DTS user request actions
    |--------------------------------------------------------------------------
    */

    Route::get(
        'dts-user-requests/{dts_user_request}/print',
        [
            DtsUserRequestController::class,
            'print',
        ]
    )->middleware('can:requests.dts_user.print');

    /*
    |--------------------------------------------------------------------------
    | Information system change request actions
    |--------------------------------------------------------------------------
    */

    Route::get(
        'is-change-requests/{is_change_request}/print',
        [
            IsChangeRequestController::class,
            'print',
        ]
    )->middleware('can:requests.is_change.print');

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::post('read-all', [NotificationController::class, 'markAllAsRead']);
    });

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

    Route::put('me/idle', [
        ProfileController::class,
        'markIdle',
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
