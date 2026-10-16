<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentationRequest;
use App\Http\Requests\UpdateDocumentationRequest;
use App\Http\Resources\DocumentationRequestResource;
use App\Models\DocumentationRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class DocumentationRequestController extends Controller
{
    /** Columns the list may be sorted by -- never pass the raw param to orderBy. */
    private const SORTABLE = [
        'control_number',
        'status',
        'requestor_name',
        'department_office',
        'date_of_request',
        'documentation_date',
        'created_at',
    ];

    public function index(Request $request)
    {
        Gate::authorize('requests.documentation.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            ...DocumentationRequest::listFilterRules(),
            'sort' => ['nullable', 'string', Rule::in(self::SORTABLE)],
            'order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = DocumentationRequest::query()
            ->with(['personnel', 'agency'])
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('control_number', 'like', "%{$search}%")
                        ->orWhere('requestor_name', 'like', "%{$search}%")
                        ->orWhere('department_office', 'like', "%{$search}%")
                        ->orWhere('program_activity_details', 'like', "%{$search}%")
                        ->orWhereHas('personnel', fn ($q) => $q->where('display_name', 'like', "%{$search}%"));
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
            'data' => DocumentationRequestResource::collection($requests),
            'meta' => [
                'total' => $requests->total(),
                'per_page' => $requests->perPage(),
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
            ],
        ]);
    }

    public function store(StoreDocumentationRequest $request)
    {
        Gate::authorize('requests.documentation.create');

        $record = DocumentationRequest::createWithControlNumber($request->validated());

        return new DocumentationRequestResource($record->refresh()->load('personnel'));
    }

    public function show(DocumentationRequest $documentationRequest)
    {
        Gate::authorize('requests.documentation.view');

        return new DocumentationRequestResource($documentationRequest->load('personnel'));
    }

    /** Form fields only -- status and personnel change through RequestWorkflowController. */
    public function update(UpdateDocumentationRequest $request, DocumentationRequest $documentationRequest)
    {
        Gate::authorize('requests.documentation.update');

        $documentationRequest->update($request->validated());

        return new DocumentationRequestResource($documentationRequest->load('personnel'));
    }

    public function destroy(DocumentationRequest $documentationRequest)
    {
        Gate::authorize('requests.documentation.delete');

        $documentationRequest->delete();

        return new DocumentationRequestResource($documentationRequest);
    }

    public function print(DocumentationRequest $documentationRequest)
    {
        Gate::authorize('requests.documentation.print');

        try {
            // 13" x 8.5" long bond, landscape (936 x 612 pt) -- the official
            // ICT-11 form prints the PGO-IT/MIS and client copies side by side.
            $pdf = Pdf::loadView('reports.documentation-request', [
                'requestRecord' => $documentationRequest->load('personnel'),
                'formCode' => config('documentation.form_code'),
                'services' => DocumentationRequest::SERVICES,
            ])->setPaper([0, 0, 936, 612]);

            $filename = implode('_', array_map(
                fn ($part) => preg_replace('/[^A-Za-z0-9\-]/', '-', (string) $part),
                [
                    'Documentation-Request',
                    $documentationRequest->control_number,
                    $documentationRequest->requestor_name,
                    now()->format('Y-m-d_Hi'),
                ]
            )).'.pdf';

            return $pdf->download($filename);
        } catch (\Throwable $e) {
            Log::error('Documentation Request PDF export failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'request_id' => $documentationRequest->id,
            ]);

            return response()->json([
                'message' => 'Failed to generate PDF request form.',
            ], 500);
        }
    }
}
