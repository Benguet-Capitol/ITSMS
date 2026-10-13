<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCctvFootageRequest;
use App\Http\Requests\UpdateCctvFootageRequest;
use App\Http\Resources\CctvFootageRequestResource;
use App\Models\CctvFootageRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CctvFootageRequestController extends Controller
{
    /** Columns the list may be sorted by -- never pass the raw param to orderBy. */
    private const SORTABLE = [
        'control_number',
        'status',
        'requester_name',
        'date_of_request',
        'date_of_incident',
        'camera_location',
        'created_at',
    ];

    public function index(Request $request)
    {
        Gate::authorize('requests.cctv_footage.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(CctvFootageRequest::STATUSES)],
            'sort' => ['nullable', 'string', Rule::in(self::SORTABLE)],
            'order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = CctvFootageRequest::query()
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('control_number', 'like', "%{$search}%")
                        ->orWhere('requester_name', 'like', "%{$search}%")
                        ->orWhere('camera_location', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status));

        if (! empty($filters['sort'])) {
            $query->orderBy($filters['sort'], $filters['order'] ?? 'asc');
        } else {
            $query->latest();
        }

        $requests = $query->paginate($filters['per_page'] ?? 5)->appends($request->query());

        return response()->json([
            'data' => CctvFootageRequestResource::collection($requests),
            'meta' => [
                'total' => $requests->total(),
                'per_page' => $requests->perPage(),
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
            ],
        ]);
    }

    public function store(StoreCctvFootageRequest $request)
    {
        Gate::authorize('requests.cctv_footage.create');

        $record = CctvFootageRequest::createWithControlNumber($request->cctvAttributes());

        return new CctvFootageRequestResource($record);
    }

    public function show(CctvFootageRequest $cctvFootageRequest)
    {
        Gate::authorize('requests.cctv_footage.view');

        return new CctvFootageRequestResource($cctvFootageRequest);
    }

    public function update(UpdateCctvFootageRequest $request, CctvFootageRequest $cctvFootageRequest)
    {
        Gate::authorize('requests.cctv_footage.update');

        $cctvFootageRequest->update($request->cctvAttributes());

        return new CctvFootageRequestResource($cctvFootageRequest);
    }

    public function destroy(CctvFootageRequest $cctvFootageRequest)
    {
        Gate::authorize('requests.cctv_footage.delete');

        $cctvFootageRequest->delete();

        return new CctvFootageRequestResource($cctvFootageRequest);
    }

    public function print(CctvFootageRequest $cctvFootageRequest)
    {
        Gate::authorize('requests.cctv_footage.print');

        try {
            $pdf = Pdf::loadView('reports.cctv-footage-request', [
                'requestRecord' => $cctvFootageRequest,
                'formCode' => config('cctv.form_code'),
                'signatories' => config('cctv.signatories'),
                'capacities' => [
                    'person_in_footage' => 'Person appearing in the footage',
                    'property_owner' => 'Owner/ possessor of property or vehicle involved',
                    'authorized_representative' => 'Authorized representative (attached SPA & valid ID)',
                    'law_enforcement' => 'Law enforcement Officer (request letter & valid ID)',
                    'court_order_holder' => 'Court Order/Subpoena Holder (Copy of Court order/Subpoena & Valid ID)',
                    'other' => 'Other Legitimate interest (Please specify)',
                ],
                'reasons' => [
                    'vehicular_accident' => 'Vehicular Accident',
                    'personal_injury' => 'Personal injury/bodily harm',
                    'theft_robbery' => 'Theft/Robbery',
                    'vandalism' => 'Vandalism',
                    'property_damage' => 'Property damage (non-theft)',
                    'security_investigation' => 'Security Investigation (internal/administrative)',
                    'law_enforcement_investigation' => 'Law enforcement investigation',
                    'legal_evidence' => 'Evidence for legal proceedings',
                ],
                // 8.5" x 13" long bond (612 x 936 pt), the paper size of the
                // official ICT-08 .docx form.
            ])->setPaper([0, 0, 612, 936], 'portrait');

            $filename = implode('_', array_map(
                fn ($part) => preg_replace('/[^A-Za-z0-9\-]/', '-', (string) $part),
                [
                    'CCTV-Footage-Request',
                    $cctvFootageRequest->control_number,
                    $cctvFootageRequest->requester_name,
                    now()->format('Y-m-d_Hi'),
                ]
            )).'.pdf';

            return $pdf->download($filename);
        } catch (\Throwable $e) {
            Log::error('CCTV Footage Request PDF export failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'request_id' => $cctvFootageRequest->id,
            ]);

            return response()->json([
                'message' => 'Failed to generate PDF request form.',
            ], 500);
        }
    }
}
