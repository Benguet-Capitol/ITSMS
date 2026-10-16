<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDesignRequest;
use App\Http\Requests\UpdateDesignRequest;
use App\Http\Resources\DesignRequestResource;
use App\Models\DesignRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class DesignRequestController extends Controller
{
    /** Columns the list may be sorted by -- never pass the raw param to orderBy. */
    private const SORTABLE = [
        'control_number',
        'status',
        'requester_name',
        'department_office',
        'date_of_request',
        'date_needed',
        'date_released',
        'created_at',
    ];

    public function index(Request $request)
    {
        Gate::authorize('requests.design.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            ...DesignRequest::listFilterRules(),
            'sort' => ['nullable', 'string', Rule::in(self::SORTABLE)],
            'order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = DesignRequest::query()
            ->with(['personnel', 'agency'])
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('control_number', 'like', "%{$search}%")
                        ->orWhere('requester_name', 'like', "%{$search}%")
                        ->orWhere('department_office', 'like', "%{$search}%")
                        ->orWhereHas('personnel', fn ($q) => $q->where('display_name', 'like', "%{$search}%"))
                        // Matches item values and "Others" specifications
                        // inside the JSON item list (e.g. "tarpaulin").
                        ->orWhere('items', 'like', "%{$search}%");
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
            'data' => DesignRequestResource::collection($requests),
            'meta' => [
                'total' => $requests->total(),
                'per_page' => $requests->perPage(),
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
            ],
        ]);
    }

    public function store(StoreDesignRequest $request)
    {
        Gate::authorize('requests.design.create');

        $record = DesignRequest::createWithControlNumber($request->designAttributes());

        return new DesignRequestResource($record->refresh()->load('personnel'));
    }

    public function show(DesignRequest $designRequest)
    {
        Gate::authorize('requests.design.view');

        return new DesignRequestResource($designRequest->load('personnel'));
    }

    /** Form fields only -- status and personnel change through RequestWorkflowController. */
    public function update(UpdateDesignRequest $request, DesignRequest $designRequest)
    {
        Gate::authorize('requests.design.update');

        $designRequest->update($request->designAttributes());

        return new DesignRequestResource($designRequest->load('personnel'));
    }

    public function destroy(DesignRequest $designRequest)
    {
        Gate::authorize('requests.design.delete');

        $designRequest->delete();

        return new DesignRequestResource($designRequest);
    }

    public function print(DesignRequest $designRequest)
    {
        Gate::authorize('requests.design.print');

        try {
            // 13" x 8.5" folio, landscape (936 x 612 pt) -- the official
            // ICT-10 sheet prints the PGO-IT/MIS and client copies side by side.
            $pdf = Pdf::loadView('reports.design-request', [
                'requestRecord' => $designRequest->load('personnel'),
                'formCode' => config('design.form_code'),
                'items' => DesignRequest::ITEMS,
                'fileFormats' => DesignRequest::FILE_FORMATS,
                'media' => DesignRequest::MEDIA,
            ])->setPaper([0, 0, 936, 612]);

            $filename = implode('_', array_map(
                fn ($part) => preg_replace('/[^A-Za-z0-9\-]/', '-', (string) $part),
                [
                    'Design-Request',
                    $designRequest->control_number,
                    $designRequest->requester_name,
                    now()->format('Y-m-d_Hi'),
                ]
            )).'.pdf';

            return $pdf->download($filename);
        } catch (\Throwable $e) {
            Log::error('Design Request PDF export failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'request_id' => $designRequest->id,
            ]);

            return response()->json([
                'message' => 'Failed to generate PDF request form.',
            ], 500);
        }
    }
}
