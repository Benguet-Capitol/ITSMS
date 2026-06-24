<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOtherItServiceRequest;
use App\Http\Requests\UpdateOtherItServiceRequest;
use App\Http\Resources\OtherItServiceRequestResource;
use App\Models\OtherItServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class OtherItServiceRequestController extends Controller
{
    public function index(Request $request) {
        Gate::authorize('requests.other_it_services.view');

        $query = OtherItServiceRequest::query();

        if ($request->has('search') && $request->search) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('control_number', 'LIKE', "%{$search}%")
                    ->orWhere('requestor_name', 'LIKE', "%{$search}%")
                    ->orWhere('department_office', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('sort')) {
            $order = $request->input('order', 'asc');
            $query->orderBy($request->sort, $order);
        } else {
            $query->latest();
        }

        $requests = $query->paginate($request->input('per_page', 5))->appends($request->query());

        return response()->json([
            'data' => OtherItServiceRequestResource::collection($requests),
            'meta' => [
                'total' => $requests->total(),
                'per_page' => $requests->perPage(),
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
            ],
        ]);
    }

    public function store(StoreOtherItServiceRequest $request) {
        Gate::authorize('requests.other_it_services.create');

        $data = $request->validated();

        $record = OtherItServiceRequest::create($data);

        return new OtherItServiceRequestResource($record);
    }

    public function show(OtherItServiceRequest $otherItServiceRequest) {
        Gate::authorize('requests.other_it_services.view');

        return new OtherItServiceRequestResource($otherItServiceRequest);
    }

    public function update(UpdateOtherItServiceRequest $request, OtherItServiceRequest $otherItServiceRequest) {
        Gate::authorize('requests.other_it_services.update');

        $data = $request->validated();

        $otherItServiceRequest->update($data);

        return new OtherItServiceRequestResource($otherItServiceRequest);
    }

    public function destroy(OtherItServiceRequest $otherItServiceRequest) {
        Gate::authorize('requests.other_it_services.delete');

        $otherItServiceRequest->delete();

        return new OtherItServiceRequestResource($otherItServiceRequest);
    }
    
    public function print(OtherItServiceRequest $otherItServiceRequest) {
        Gate::authorize('requests.other_it_services.print');

        try {
            $pdf = Pdf::loadView('reports.other-it-service-request', [
                'requestRecord' => $otherItServiceRequest,
                'generatedAt'   => now(),
            ])->setPaper('letter', 'portrait');

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $filenameParts = [
                'Other-IT-Service-Request',
                $otherItServiceRequest->control_number ?: 'No-Control-No',
                $otherItServiceRequest->requestor_name ?: 'Unknown-Requestor',
                now()->format('Y-m-d_Hi'),
            ];

            $filename = implode('_', array_map(
                fn ($part) => preg_replace('/[^A-Za-z0-9\-]/', '-', $part),
                $filenameParts 
            )) . '.pdf';

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            return $pdf->download($filename);
        } catch (\Throwable $e) {
            Log::error('Other IT Service Request PDF export failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
                'request_id' => $otherItServiceRequest->id,
            ]);

            return response()->json([
                'message' => 'Failed to generate PDF request form.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
