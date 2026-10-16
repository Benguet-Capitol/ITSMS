<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIsChangeRequest;
use App\Http\Requests\UpdateIsChangeRequest;
use App\Http\Resources\IsChangeRequestResource;
use App\Models\IsChangeRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class IsChangeRequestController extends Controller
{
    /** Columns the list may be sorted by -- never pass the raw param to orderBy. */
    private const SORTABLE = [
        'control_number',
        'status',
        'stage',
        'request_type',
        'priority',
        'information_system',
        'department_office',
        'date_of_request',
        'date_required',
        'created_at',
    ];

    public function index(Request $request)
    {
        Gate::authorize('requests.is_change.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            ...IsChangeRequest::listFilterRules(),
            'stage' => ['nullable', 'string', Rule::in(array_keys(IsChangeRequest::STAGES))],
            'request_type' => ['nullable', 'string', Rule::in(array_keys(IsChangeRequest::REQUEST_TYPES))],
            'priority' => ['nullable', 'string', Rule::in(array_keys(IsChangeRequest::PRIORITIES))],
            'sort' => ['nullable', 'string', Rule::in(self::SORTABLE)],
            'order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = IsChangeRequest::query()
            ->with('personnel')
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('control_number', 'like', "%{$search}%")
                        ->orWhere('information_system', 'like', "%{$search}%")
                        ->orWhere('department_office', 'like', "%{$search}%")
                        ->orWhere('modules_affected', 'like', "%{$search}%")
                        ->orWhere('requested_by', 'like', "%{$search}%")
                        ->orWhereHas('personnel', fn ($q) => $q->where('display_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($filters['stage'] ?? null, fn ($q, string $stage) => $q->where('stage', $stage))
            ->forTab($filters['tab'] ?? null, Auth::user()->profile)
            ->when($filters['request_type'] ?? null, fn ($q, string $type) => $q->where('request_type', $type))
            ->when($filters['priority'] ?? null, fn ($q, string $priority) => $q->where('priority', $priority));

        if (($filters['sort'] ?? null) === 'priority') {
            // By severity (low -> critical), not alphabetically.
            $direction = ($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
            $cases = collect(array_keys(IsChangeRequest::PRIORITIES))
                ->map(fn (string $priority, int $rank) => "WHEN '{$priority}' THEN {$rank}")
                ->implode(' ');
            $query->orderByRaw("CASE priority {$cases} END {$direction}");
        } elseif (! empty($filters['sort'])) {
            $query->orderBy($filters['sort'], $filters['order'] ?? 'asc');
        } else {
            $query->latest();
        }

        $requests = $query->paginate($filters['per_page'] ?? 5)->appends($request->query());

        return response()->json([
            'data' => IsChangeRequestResource::collection($requests),
            'meta' => [
                'total' => $requests->total(),
                'per_page' => $requests->perPage(),
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
            ],
        ]);
    }

    public function store(StoreIsChangeRequest $request)
    {
        Gate::authorize('requests.is_change.create');

        $record = IsChangeRequest::createWithControlNumber($request->isChangeAttributes());

        return new IsChangeRequestResource($record->refresh()->load('personnel'));
    }

    public function show(IsChangeRequest $isChangeRequest)
    {
        Gate::authorize('requests.is_change.view');

        return new IsChangeRequestResource($isChangeRequest->load('personnel'));
    }

    /**
     * Form fields and the development step only -- status and personnel
     * change through RequestWorkflowController.
     */
    public function update(UpdateIsChangeRequest $request, IsChangeRequest $isChangeRequest)
    {
        Gate::authorize('requests.is_change.update');

        $isChangeRequest->update($request->isChangeAttributes());

        return new IsChangeRequestResource($isChangeRequest->load('personnel'));
    }

    public function destroy(IsChangeRequest $isChangeRequest)
    {
        Gate::authorize('requests.is_change.delete');

        $isChangeRequest->delete();

        return new IsChangeRequestResource($isChangeRequest);
    }

    public function print(IsChangeRequest $isChangeRequest)
    {
        Gate::authorize('requests.is_change.print');

        try {
            // 8.5" x 13" portrait (612 x 936 pt), single copy -- the
            // official form has no separate IT/client copies.
            $pdf = Pdf::loadView('reports.is-change-request', [
                'requestRecord' => $isChangeRequest->load('personnel'),
                'formCode' => config('is_change.form_code'),
                'requestTypes' => IsChangeRequest::REQUEST_TYPES,
                'priorities' => IsChangeRequest::PRIORITIES,
            ])->setPaper([0, 0, 612, 936]);

            $filename = implode('_', array_map(
                fn ($part) => preg_replace('/[^A-Za-z0-9\-]/', '-', (string) $part),
                [
                    'IS-Change-Request',
                    $isChangeRequest->control_number,
                    $isChangeRequest->information_system,
                    now()->format('Y-m-d_Hi'),
                ]
            )).'.pdf';

            return $pdf->download($filename);
        } catch (\Throwable $e) {
            Log::error('IS Change Request PDF export failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'request_id' => $isChangeRequest->id,
            ]);

            return response()->json([
                'message' => 'Failed to generate PDF request form.',
            ], 500);
        }
    }
}
