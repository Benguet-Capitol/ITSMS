<?php

namespace App\Http\Controllers;

use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Support\RequestTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Reads the activity log (docs/activity-log.md). Nothing here changes it.
 *
 * With subject_type + subject_id it's one record's history, which anyone
 * who may view that record can see. Otherwise it's the whole log, which
 * needs activity_logs.view.
 */
class ActivityLogController extends Controller
{
    /** subject_type => [morph type stored in the log, permission to view it] */
    public static function subjectTypes(): array
    {
        $types = [
            'ticket' => [(new Ticket)->getMorphClass(), 'tickets.view'],
            'inventory' => [(new Inventory)->getMorphClass(), 'inventories.view'],
            'user' => [(new User)->getMorphClass(), 'users.view'],
            'role' => [(new Role)->getMorphClass(), 'roles.view'],
        ];

        foreach (RequestTypes::TYPES as $key => $config) {
            $types[$key] = [(new $config['model'])->getMorphClass(), "{$config['permission']}.view"];
        }

        return $types;
    }

    public function index(Request $request)
    {
        $types = self::subjectTypes();

        $data = $request->validate([
            'subject_type' => ['nullable', Rule::in(array_keys($types))],
            'subject_id' => ['nullable', 'required_with:subject_type', 'string', 'max:36'],
            'module' => ['nullable', Rule::in([...array_keys($types), 'sign_in', 'report'])],
            'action' => ['nullable', 'string', 'max:64'],
            'user_id' => ['nullable', 'string', 'max:36'],
            'search' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $forOneRecord = ! empty($data['subject_type']);

        Gate::authorize($forOneRecord ? $types[$data['subject_type']][1] : 'activity_logs.view');

        $query = ActivityLog::query()
            ->when($forOneRecord, fn ($q) => $q
                ->where('loggable_type', $types[$data['subject_type']][0])
                ->where('loggable_id', $data['subject_id']))
            ->when($data['module'] ?? null, function ($q, string $module) use ($types) {
                match ($module) {
                    'sign_in' => $q->whereIn('action', ['login', 'logout', 'login_failed']),
                    'report' => $q->where('action', 'report'),
                    default => $q->where('loggable_type', $types[$module][0])
                        ->whereNotIn('action', ['login', 'logout', 'login_failed', 'report']),
                };
            })
            ->when($data['action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->when($data['user_id'] ?? null, fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($data['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('subject_label', 'like', "%{$search}%")
                ->orWhere('user_name', 'like', "%{$search}%")))
            ->when($data['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($data['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $logs = $query->paginate($data['per_page'] ?? 25)->appends($request->query());

        return response()->json([
            'data' => ActivityLogResource::collection($logs),
            'meta' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                // Every action in the log, for the filter.
                'actions' => $forOneRecord ? [] : ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
            ],
        ]);
    }
}
