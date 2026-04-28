<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\HrisClientService;
use App\Http\Resources\OfficeResource;
use Illuminate\Pagination\LengthAwarePaginator;

class OfficeController extends Controller
{
    public function index(Request $request, HrisClientService $hris) {
        $offices = collect($hris->getOfficesCached(minutes: 30));

        if ($request->filled('search')) {
            $search = mb_strtolower($request->input('search'));

            $offices = $offices->filter(function ($office) use ($search) {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $office['office_code'] ?? '',
                    $office['office_desc'] ?? '',
                ])));

                return str_contains($haystack, $search);
            })->values();
        }

        $sort = $request->input('sort', 'office_code');
        $order = strtolower($request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortKeyMap = [
            'office_code' => 'office_code',
            'office_desc' => 'office_desc',
            'code' => 'office_code',
            'name' => 'office_desc',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];

        $hrisKey = $sortKeyMap[$sort] ?? 'office_code';

        $offices = $offices->sortBy(
            fn ($office) => $office[$hrisKey] ?? null,
            SORT_REGULAR,
            $order === 'desc'
        )->values();

        $perPage = (int) $request->input('per_page', 5);
        $page = (int) $request->input('page', 1);
        $total = $offices->count();
        $items = $offices->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'data' => OfficeResource::collection($paginator->getCollection()),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function search(Request $request, HrisClientService $hris) {
        $q = trim((string) $request->get('q', ''));
        $limit = (int) $request->get('limit', 20);

        if (mb_strlen($q) < 1) {
            return response()->json(['data' => []]);
        }

        $rows = $hris->searchOffices($q, $limit);

        $data = collect($rows)->map(fn ($office) => [
            'id' => $office['id'] ?? null,
            'office_code' => $office['office_code'] ?? null,
            'office_desc' => $office['office_desc'] ?? null,
            'label' => ($office['office_code'] ?? '') . ' - ' . ($office['office_desc'] ?? ''),
        ])->values();

        return response()->json(['data' => $data]);
    }
}
