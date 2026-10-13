<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDtsUserRequest;
use App\Http\Requests\UpdateDtsUserRequest;
use App\Http\Resources\DtsUserRequestResource;
use App\Models\DtsUserRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class DtsUserRequestController extends Controller
{
    /** Columns the list may be sorted by -- never pass the raw param to orderBy. */
    private const SORTABLE = [
        'control_number',
        'status',
        'request_type',
        'full_name',
        'department',
        'dts_username',
        'date_of_request',
        'date_created',
        'created_at',
    ];

    public function index(Request $request)
    {
        Gate::authorize('requests.dts_user.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(DtsUserRequest::STATUSES)],
            'request_type' => ['nullable', 'string', Rule::in(array_keys(DtsUserRequest::REQUEST_TYPES))],
            'sort' => ['nullable', 'string', Rule::in(self::SORTABLE)],
            'order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = DtsUserRequest::query()
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('control_number', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%")
                        ->orWhere('dts_username', 'like', "%{$search}%")
                        ->orWhere('requested_by', 'like', "%{$search}%")
                        ->orWhere('assigned_personnel', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($filters['request_type'] ?? null, fn ($q, string $type) => $q->where('request_type', $type));

        if (! empty($filters['sort'])) {
            $query->orderBy($filters['sort'], $filters['order'] ?? 'asc');
        } else {
            $query->latest();
        }

        $requests = $query->paginate($filters['per_page'] ?? 5)->appends($request->query());

        return response()->json([
            'data' => DtsUserRequestResource::collection($requests),
            'meta' => [
                'total' => $requests->total(),
                'per_page' => $requests->perPage(),
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
            ],
        ]);
    }

    public function store(StoreDtsUserRequest $request)
    {
        Gate::authorize('requests.dts_user.create');

        $record = DtsUserRequest::createWithControlNumber($request->dtsUserAttributes());

        return new DtsUserRequestResource($record);
    }

    public function show(DtsUserRequest $dtsUserRequest)
    {
        Gate::authorize('requests.dts_user.view');

        return new DtsUserRequestResource($dtsUserRequest);
    }

    public function update(UpdateDtsUserRequest $request, DtsUserRequest $dtsUserRequest)
    {
        Gate::authorize('requests.dts_user.update');

        $dtsUserRequest->update($request->dtsUserAttributes());

        return new DtsUserRequestResource($dtsUserRequest);
    }

    public function destroy(DtsUserRequest $dtsUserRequest)
    {
        Gate::authorize('requests.dts_user.delete');

        $dtsUserRequest->delete();

        return new DtsUserRequestResource($dtsUserRequest);
    }

    public function print(DtsUserRequest $dtsUserRequest)
    {
        Gate::authorize('requests.dts_user.print');

        try {
            // 8.5" x 13" portrait (612 x 936 pt) -- the official form stacks
            // the PGO-IT/MIS file copy above the client copy on one page.
            $pdf = Pdf::loadView('reports.dts-user-request', [
                'requestRecord' => $dtsUserRequest,
                'formCode' => config('dts_user.form_code'),
                'requestTypes' => DtsUserRequest::REQUEST_TYPES,
            ])->setPaper([0, 0, 612, 936]);

            $filename = implode('_', array_map(
                fn ($part) => preg_replace('/[^A-Za-z0-9\-]/', '-', (string) $part),
                [
                    'DTS-User-Request',
                    $dtsUserRequest->control_number,
                    $dtsUserRequest->full_name,
                    now()->format('Y-m-d_Hi'),
                ]
            )).'.pdf';

            return $pdf->download($filename);
        } catch (\Throwable $e) {
            Log::error('DTS User Request PDF export failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'request_id' => $dtsUserRequest->id,
            ]);

            return response()->json([
                'message' => 'Failed to generate PDF request form.',
            ], 500);
        }
    }
}
