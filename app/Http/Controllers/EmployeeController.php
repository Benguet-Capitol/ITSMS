<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Http\Resources\EmployeeResource;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Services\HrisClientService;
use Illuminate\Support\Facades\Cache;

class EmployeeController extends Controller
{
    public function index(Request $request, HrisClientService $hris) {
        $employees = collect($hris->getEmployeesCached(minutes: 5));

        // SEARCH
        if ($request->filled('search')) {
            $search = mb_strtolower($request->input('search'));

            $employees = $employees->filter(function ($e) use ($search) {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $e['employee_id_number'] ?? '',
                    $e['fname']             ?? '',
                    $e['mname']             ?? '',
                    $e['lname']             ?? '',
                    $e['office_desc']       ?? '',
                    $e['position_title']    ?? '',
                    $e['employee_type']     ?? '',
                ])));

                return str_contains($haystack, $search);
            })->values();
        }

        // SORTING
        $sort = $request->input('sort');
        if ($sort) {
            $order = strtolower($request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';

            $sortKeyMap = [
                'fullname'       => 'fullname',
                'fname'          => 'fname',
                'mname'          => 'mname',
                'lname'          => 'lname',
                'office_desc'    => 'office_desc',
                'office_code'    => 'office_code',
                'position_title' => 'position_title',
                'type'           => 'type',
                'salary_grade_id'=> 'salary_grade_id',
                'grade'          => 'grade',
                'division'       => 'division',
                'unit'           => 'unit',
                'salary'         => 'salary',
                // UI aliases
                'firstname'      => 'fname',
                'lastname'       => 'lname',
            ];

            $hrisKey   = $sortKeyMap[$sort] ?? $sort;
            $employees = $employees->sortBy(
                fn($e) => $e[$hrisKey] ?? null,
                SORT_REGULAR,
                $order === 'desc'
            )->values();
        }

        // PAGINATION
        $perPage = (int) $request->input('per_page', 5);
        $page    = (int) $request->input('page', 1);
        $total   = $employees->count();
        $items   = $employees->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'data' => EmployeeResource::collection($paginator->getCollection()),
            'meta' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    public function search(Request $request, HrisClientService $hris) {
        $q     = trim((string) $request->get('q', ''));
        $limit = (int) $request->get('limit', 20);

        if (mb_strlen($q) < 2 && !$request->hasAny(['employee_id', 'office_id', 'type'])) {
            return response()->json(['data' => []]);
        }

        $allowed = array_keys(config('hris.employee_filters', []));
        $filters = $request->only($allowed);

        if (!isset($filters['employee_id']) && preg_match('/^\d{6,}$/', $q)) {
            $filters['employee_id'] = $q;
        }

        $rows = $hris->getEmployeesWithParams($filters);
        $didUseEmployeeId = isset($filters['employee_id']) && $filters['employee_id'] === $q;

        if ($q !== '' && !$didUseEmployeeId) {
            $needle = mb_strtolower($q);
            $rows   = collect($rows)->filter(function ($e) use ($needle) {
                $name = mb_strtolower($e['fullname'] ?? $e['full_name'] ?? '');
                return $name !== '' && str_contains($name, $needle);
            })->values()->all();
        }

        $data = collect($rows)
            ->take($limit)
            ->map(fn($e) => [
                'id'                 => $e['id']                 ?? null,
                'employee_id_number' => $e['employee_id_number'] ?? null,
                'full_name'          => $e['fullname'] ?? $e['full_name'] ?? null,
                'office_id'          => $e['office_id']          ?? null,
                'office_code'        => $e['office_code']        ?? null,
                'position_title'     => $e['position_title']     ?? null,
                'type'               => $e['type']               ?? null,
            ])
            ->filter(fn($e) => $e['id'] && $e['employee_id_number'] && $e['full_name'])
            ->values();

        return response()->json(['data' => $data]);
    }
}
