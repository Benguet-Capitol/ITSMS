<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInspectionRequest;
use App\Http\Requests\UpdateInspectionRequest;
use App\Http\Resources\InspectionRequestResource;
use App\Models\InspectionRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class InspectionRequestController extends Controller
{
    /** Columns the list may be sorted by -- never pass the raw param to orderBy. */
    private const SORTABLE = [
        'control_number',
        'status',
        'end_user_office',
        'procurement_mode',
        'po_number',
        'supplier',
        'date_of_request',
        'inspection_date',
        'created_at',
    ];

    /** Loaded for every response: the workflow personnel and the inspectors. */
    private const RELATIONS = ['personnel', 'inspectors'];

    public function index(Request $request)
    {
        Gate::authorize('requests.inspection.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            ...InspectionRequest::listFilterRules(),
            'sort' => ['nullable', 'string', Rule::in(self::SORTABLE)],
            'order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = InspectionRequest::query()
            ->with(self::RELATIONS)
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('control_number', 'like', "%{$search}%")
                        ->orWhere('end_user_office', 'like', "%{$search}%")
                        ->orWhere('office_code', 'like', "%{$search}%")
                        ->orWhere('po_number', 'like', "%{$search}%")
                        ->orWhere('supplier', 'like', "%{$search}%")
                        ->orWhereHas('personnel', fn ($q) => $q->where('display_name', 'like', "%{$search}%"))
                        ->orWhereHas('inspectors', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                        // Item descriptions and brands inside the JSON list.
                        // JSON compares case-sensitively, so lower both sides.
                        ->orWhereRaw('LOWER(CAST(items AS CHAR)) LIKE ?', ['%'.mb_strtolower($search).'%']);
                });
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->forTab($filters['tab'] ?? null, Auth::user()->profile);

        if (! empty($filters['sort'])) {
            $query->orderBy($filters['sort'], $filters['order'] ?? 'asc');
        } else {
            $query->latest();
        }

        $requests = $query->paginate($filters['per_page'] ?? 5)->appends($request->query());

        return response()->json([
            'data' => InspectionRequestResource::collection($requests),
            'meta' => [
                'total' => $requests->total(),
                'per_page' => $requests->perPage(),
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
            ],
        ]);
    }

    public function store(StoreInspectionRequest $request)
    {
        Gate::authorize('requests.inspection.create');

        $record = DB::transaction(function () use ($request) {
            $record = InspectionRequest::createWithControlNumber($request->inspectionAttributes());
            $record->inspectors()->sync($request->inspectorIds());

            return $record;
        });

        return new InspectionRequestResource($record->refresh()->load(self::RELATIONS));
    }

    public function show(InspectionRequest $inspectionRequest)
    {
        Gate::authorize('requests.inspection.view');

        return new InspectionRequestResource($inspectionRequest->load(self::RELATIONS));
    }

    /** Form fields only -- status and personnel change through RequestWorkflowController. */
    public function update(UpdateInspectionRequest $request, InspectionRequest $inspectionRequest)
    {
        Gate::authorize('requests.inspection.update');

        DB::transaction(function () use ($request, $inspectionRequest) {
            $inspectionRequest->update($request->inspectionAttributes());
            $inspectionRequest->inspectors()->sync($request->inspectorIds());
        });

        return new InspectionRequestResource($inspectionRequest->load(self::RELATIONS));
    }

    public function destroy(InspectionRequest $inspectionRequest)
    {
        Gate::authorize('requests.inspection.delete');

        $inspectionRequest->delete();

        return new InspectionRequestResource($inspectionRequest);
    }

    public function print(InspectionRequest $inspectionRequest)
    {
        Gate::authorize('requests.inspection.print');

        try {
            // 8.5" x 13" folio, portrait (612 x 936 pt), like the other forms' paper.
            $pdf = Pdf::loadView('reports.inspection-request', [
                'requestRecord' => $inspectionRequest->load(self::RELATIONS),
                'results' => InspectionRequest::RESULTS,
            ])->setPaper([0, 0, 612, 936]);

            $filename = implode('_', array_map(
                fn ($part) => preg_replace('/[^A-Za-z0-9\-]/', '-', (string) $part),
                [
                    'Inspection-Report',
                    $inspectionRequest->control_number,
                    $inspectionRequest->office_code ?: $inspectionRequest->end_user_office,
                    now()->format('Y-m-d_Hi'),
                ]
            )).'.pdf';

            return $pdf->download($filename);
        } catch (\Throwable $e) {
            Log::error('Inspection Report PDF export failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'request_id' => $inspectionRequest->id,
            ]);

            return response()->json([
                'message' => 'Failed to generate the PDF inspection report.',
            ], 500);
        }
    }
}
