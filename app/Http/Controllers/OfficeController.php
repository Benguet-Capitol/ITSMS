<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\HrisClientService;
use App\Http\Resources\OfficeResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class OfficeController extends Controller
{
    public function index(Request $request, HrisClientService $hris) {
        Gate::authorize('offices.view');

        $offices = collect(
            $hris->getOfficesCached(minutes: 30)
        );

        if ($request->filled('search')) {
            $search = mb_strtolower(
                trim($request->string('search')->toString())
            );

            if ($search !== '') {
                $offices = $offices
                    ->filter(function ($office) use ($search) {
                        $haystack = mb_strtolower(
                            implode(' ', array_filter([
                                $office['office_code'] ?? '',
                                $office['office_desc'] ?? '',
                            ]))
                        );

                        return str_contains($haystack, $search);
                    })
                    ->values();
            }
        }

        $sort = $request->input(
            'sort',
            'office_code'
        );

        $order = strtolower(
            $request->input('order', 'asc')
        ) === 'desc'
            ? 'desc'
            : 'asc';

        $sortKeyMap = [
            'office_code' => 'office_code',
            'office_desc' => 'office_desc',
            'code' => 'office_code',
            'name' => 'office_desc',
        ];

        $hrisKey = $sortKeyMap[$sort] ?? 'office_code';

        $offices = $offices
            ->sortBy(
                fn ($office) => mb_strtolower(
                    (string) ($office[$hrisKey] ?? '')
                ),
                SORT_STRING,
                $order === 'desc'
            )
            ->values();

        $perPage = min(
            max($request->integer('per_page', 5), 1),
            100
        );

        $page = max(
            $request->integer('page', 1),
            1
        );

        $total = $offices->count();

        $items = $offices
            ->slice(($page - 1) * $perPage, $perPage)
            ->values();

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return response()->json([
            'data' => OfficeResource::collection(
                $paginator->getCollection()
            ),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function search(Request $request, HrisClientService $hris) {
        Gate::authorize('offices.search');

        $query = trim(
            $request->string('q')->toString()
        );

        $limit = min(
            max($request->integer('limit', 20), 1),
            100
        );

        if ($query === '') {
            return response()->json([
                'data' => [],
            ]);
        }

        $rows = $hris->searchOffices(
            $query,
            $limit
        );

        return response()->json([
            'data' => OfficeResource::collection(
                collect($rows)->values()
            ),
        ]);
    }
}
