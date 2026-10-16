<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNetworkAssistanceRequest;
use App\Http\Requests\UpdateNetworkAssistanceRequest;
use App\Http\Resources\NetworkAssistanceRequestResource;
use App\Models\NetworkAssistanceRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class NetworkAssistanceRequestController extends Controller
{
    /** Columns the list may be sorted by -- never pass the raw param to orderBy. */
    private const SORTABLE = [
        'control_number',
        'status',
        'requestor_name',
        'department_office',
        'date_of_request',
        'activity_date',
        'venue',
        'created_at',
    ];

    public function index(Request $request)
    {
        Gate::authorize('requests.network_assistance.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            ...NetworkAssistanceRequest::listFilterRules(),
            'sort' => ['nullable', 'string', Rule::in(self::SORTABLE)],
            'order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = NetworkAssistanceRequest::query()
            ->with(['personnel', 'agency'])
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('control_number', 'like', "%{$search}%")
                        ->orWhere('requestor_name', 'like', "%{$search}%")
                        ->orWhere('department_office', 'like', "%{$search}%")
                        ->orWhere('program_activity_details', 'like', "%{$search}%")
                        ->orWhere('venue', 'like', "%{$search}%")
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
            'data' => NetworkAssistanceRequestResource::collection($requests),
            'meta' => [
                'total' => $requests->total(),
                'per_page' => $requests->perPage(),
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
            ],
        ]);
    }

    public function store(StoreNetworkAssistanceRequest $request)
    {
        Gate::authorize('requests.network_assistance.create');

        $record = NetworkAssistanceRequest::createWithControlNumber($request->validated());

        return new NetworkAssistanceRequestResource($record->refresh()->load('personnel'));
    }

    public function show(NetworkAssistanceRequest $networkAssistanceRequest)
    {
        Gate::authorize('requests.network_assistance.view');

        return new NetworkAssistanceRequestResource($networkAssistanceRequest->load('personnel'));
    }

    /** Form fields only -- status and personnel change through RequestWorkflowController. */
    public function update(UpdateNetworkAssistanceRequest $request, NetworkAssistanceRequest $networkAssistanceRequest)
    {
        Gate::authorize('requests.network_assistance.update');

        $networkAssistanceRequest->update($request->validated());

        return new NetworkAssistanceRequestResource($networkAssistanceRequest->load('personnel'));
    }

    public function destroy(NetworkAssistanceRequest $networkAssistanceRequest)
    {
        Gate::authorize('requests.network_assistance.delete');

        $networkAssistanceRequest->delete();

        return new NetworkAssistanceRequestResource($networkAssistanceRequest);
    }

    public function print(NetworkAssistanceRequest $networkAssistanceRequest)
    {
        Gate::authorize('requests.network_assistance.print');

        try {
            // Letter portrait -- the official ICT-15 sheet stacks the
            // PGO-IT/MIS file copy above the client copy on one page.
            $pdf = Pdf::loadView('reports.network-assistance-request', [
                'requestRecord' => $networkAssistanceRequest->load('personnel'),
                'formCode' => config('network_assistance.form_code'),
                'services' => NetworkAssistanceRequest::SERVICES,
            ])->setPaper('letter', 'portrait');

            $filename = implode('_', array_map(
                fn ($part) => preg_replace('/[^A-Za-z0-9\-]/', '-', (string) $part),
                [
                    'Network-Assistance-Request',
                    $networkAssistanceRequest->control_number,
                    $networkAssistanceRequest->requestor_name,
                    now()->format('Y-m-d_Hi'),
                ]
            )).'.pdf';

            return $pdf->download($filename);
        } catch (\Throwable $e) {
            Log::error('Network Assistance Request PDF export failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'request_id' => $networkAssistanceRequest->id,
            ]);

            return response()->json([
                'message' => 'Failed to generate PDF request form.',
            ], 500);
        }
    }
}
