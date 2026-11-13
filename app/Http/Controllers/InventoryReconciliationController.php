<?php

namespace App\Http\Controllers;

use App\Jobs\SyncPmsProperties;
use App\Models\Inventory;
use App\Models\PmsInventoryLink;
use App\Models\PmsProperty;
use App\Models\PmsReconciliationDismissal;
use App\Models\PmsSyncRun;
use App\Rules\AvailablePropertyNumber;
use App\Services\ActivityLogger;
use App\Services\PmsPropertySync;
use App\Services\PmsReconciliation;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Inventory counter-checked against PGSO's property list
 * (docs/inventory-reconciliation.md). Lists the differences and lets staff
 * fix each one deliberately: take the PMS value, link an item to its PMS
 * property, or keep ITSMS as it is. PMS itself is never written to.
 *
 * Permissions come from the routes: inventory_reconciliation.view to look,
 * inventory_reconciliation.manage to act, and inventories.update as well
 * for anything that changes an item.
 */
class InventoryReconciliationController extends Controller
{
    public function index(Request $request, PmsReconciliation $reconciliation, PmsPropertySync $sync)
    {
        $data = $request->validate([
            'issue' => ['nullable', Rule::in(PmsReconciliation::ISSUES)],
            'dismissed' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $issues = $reconciliation->issues();
        $counts = [];

        foreach (PmsReconciliation::ISSUES as $issue) {
            $ofIssue = $issues->where('issue', $issue);
            $counts[$issue] = [
                'open' => $ofIssue->whereNull('dismissal')->count(),
                'dismissed' => $ofIssue->whereNotNull('dismissal')->count(),
            ];
        }

        $issue = $data['issue'] ?? PmsReconciliation::ISSUES[0];
        $showDismissed = (bool) ($data['dismissed'] ?? false);
        $search = mb_strtolower(trim($data['search'] ?? ''));

        $rows = $issues
            ->where('issue', $issue)
            ->filter(fn (array $item) => ($item['dismissal'] !== null) === $showDismissed)
            ->when($search !== '', fn ($rows) => $rows->filter(fn (array $item) => str_contains(mb_strtolower(implode(' ', array_filter([
                $item['inventory']['property_number'] ?? null,
                $item['inventory']['item_type'] ?? null,
                $item['inventory']['office_code'] ?? null,
                $item['inventory']['serial_number'] ?? null,
                $item['pms']['property_no'] ?? null,
                $item['pms']['property_name'] ?? null,
                $item['pms']['office_code'] ?? null,
                $item['pms']['full_name'] ?? null,
            ]))), $search)))
            ->sortBy(fn (array $item) => $item['inventory']['property_number'] ?? $item['pms']['property_no'] ?? '', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $perPage = (int) ($data['per_page'] ?? 25);
        $page = (int) ($data['page'] ?? 1);
        $paginator = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page);

        $lastRun = PmsSyncRun::with('triggeredBy:id,display_name')->latest('id')->first();
        $lastSuccess = PmsSyncRun::where('status', PmsSyncRun::SUCCEEDED)->latest('id')->first();

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'issue' => $issue,
                'counts' => $counts,
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'configured' => PmsPropertySync::isConfigured(),
                'pms_count' => PmsProperty::count(),
                'synced_at' => $lastSuccess?->finished_at?->toIso8601String(),
                'last_run' => $lastRun?->toSummary(),
                'running' => $sync->activeRun() !== null,
            ],
        ]);
    }

    /** One item against PMS, for the inventory item's page. */
    public function show(Inventory $inventory, PmsReconciliation $reconciliation)
    {
        $lastSuccess = PmsSyncRun::where('status', PmsSyncRun::SUCCEEDED)->latest('id')->first();

        return response()->json([
            'data' => [
                ...$reconciliation->forInventory($inventory->id),
                'inventory' => $reconciliation->inventorySummaryFor($inventory->id),
                'synced_at' => $lastSuccess?->finished_at?->toIso8601String(),
            ],
        ]);
    }

    /** PMS badges for a page of the inventory list: {id: {status, open}}. */
    public function statuses(Request $request, PmsReconciliation $reconciliation)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        return response()->json([
            'data' => (object) $reconciliation->statuses(array_map('intval', $data['ids'])),
        ]);
    }

    /** Starts a sync in the background; the page follows it through syncStatus(). */
    public function sync(PmsPropertySync $sync)
    {
        abort_unless(PmsPropertySync::isConfigured(), 422, 'The PMS connection is not set up (PMS_BASE_URL and PMS_API_KEY).');

        $run = $sync->start(Auth::user()?->profile?->id);

        if (! $run) {
            return response()->json([
                'message' => 'A sync is already running.',
                'data' => $sync->activeRun()?->toSummary(),
            ], 409);
        }

        SyncPmsProperties::dispatchAfterResponse($run);

        return response()->json([
            'message' => 'Sync started. It takes about half a minute.',
            'data' => $run->load('triggeredBy:id,display_name')->toSummary(),
        ], 202);
    }

    public function syncStatus(PmsPropertySync $sync)
    {
        $sync->activeRun();

        return response()->json([
            'data' => PmsSyncRun::with('triggeredBy:id,display_name')->latest('id')->first()?->toSummary(),
        ]);
    }

    /** Sets the item's office to the HRIS office its PMS office is mapped to. */
    public function acceptOffice(Inventory $inventory, PmsReconciliation $reconciliation, ActivityLogger $logger)
    {
        $item = $reconciliation->issueFor('office', $inventory->id);

        abort_unless($item, 422, "{$inventory->property_number}'s office already agrees with PMS.");

        $mapping = $item['office_mapping'];

        abort_unless($mapping, 422, "PMS office \"{$item['pms']['office_code']}\" isn't mapped to an HRIS office yet. Map it first, under Office mapping.");

        $officeChanged = (int) $inventory->office_id !== $mapping['office_id'];

        $inventory->update([
            'office_id' => $mapping['office_id'],
            'office_code' => $mapping['office_code'],
            'office_name' => $mapping['office_name'],
            // A division belongs to the old office.
            ...($officeChanged ? ['division_id' => null, 'division_name' => null] : []),
        ]);

        $logger->addContext($inventory, ['source' => 'PMS', 'pms_property_no' => $item['pms']['property_no'], 'pms_office' => $item['pms']['office_code']]);

        return response()->json(['message' => "{$inventory->property_number} is now at {$mapping['office_code']}."]);
    }

    /** Sets the item's serial number to the one in its PMS details. */
    public function acceptSerial(Inventory $inventory, PmsReconciliation $reconciliation, ActivityLogger $logger)
    {
        $item = $reconciliation->issueFor('serial', $inventory->id);

        abort_unless($item, 422, "{$inventory->property_number}'s serial number already agrees with PMS.");

        $inventory->update(['serial_number' => $item['pms']['serial_number']]);

        $logger->addContext($inventory, ['source' => 'PMS', 'pms_property_no' => $item['pms']['property_no']]);

        return response()->json(['message' => "{$inventory->property_number}'s serial number is now {$item['pms']['serial_number']}."]);
    }

    /**
     * Marks items PMS lists as returned to PGSO as returned in ITSMS too,
     * keeping them on record (docs/inventory-status.md). Either the given
     * items or, with `all`, every open item on the Returned to PGSO list.
     * Only items still on that list are changed.
     */
    public function markReturned(Request $request, PmsReconciliation $reconciliation, ActivityLogger $logger)
    {
        $data = $request->validate([
            'all' => ['nullable', 'boolean'],
            'inventory_ids' => ['required_without:all', 'array', 'max:500'],
            'inventory_ids.*' => ['integer', 'distinct'],
        ]);

        $onList = $reconciliation->issues()
            ->where('issue', 'returned')
            ->whereNull('dismissal');

        $wanted = ($data['all'] ?? false)
            ? $onList
            : $onList->filter(fn (array $item) => in_array($item['inventory']['id'], $data['inventory_ids'] ?? [], true));

        abort_if($wanted->isEmpty(), 422, 'None of these are on the Returned to PGSO list any more. Refresh the list.');

        $items = Inventory::query()->setEagerLoads([])
            ->whereIn('id', $wanted->pluck('inventory.id'))
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($items, $wanted, $logger) {
            foreach ($wanted as $issue) {
                $inventory = $items[$issue['inventory']['id']];

                // Not route-bound, so the log has to be told before the change.
                $logger->watch($inventory);
                $inventory->update([
                    'status' => Inventory::STATUS_RETURNED,
                    'status_changed_at' => now()->toDateString(),
                    'status_note' => null,
                ]);

                $logger->addContext($inventory, ['source' => 'PMS', 'pms_property_no' => $issue['pms']['property_no'], 'pms_office' => $issue['pms']['office_code']]);
            }
        });

        $count = $items->count();

        return response()->json([
            'message' => $count === 1
                ? "{$items->first()->property_number} is now returned to PGSO."
                : "{$count} items are now returned to PGSO.",
            'data' => ['count' => $count],
        ]);
    }

    /**
     * An item PGSO issued again: active, at the HRIS office its PMS office
     * is mapped to.
     */
    public function reactivate(Inventory $inventory, PmsReconciliation $reconciliation, ActivityLogger $logger)
    {
        $item = $reconciliation->issueFor('reissued', $inventory->id);

        abort_unless($item, 422, "PMS doesn't list {$inventory->property_number} at an office, or it's already active.");

        $mapping = $item['office_mapping'];

        abort_unless($mapping, 422, "PMS office \"{$item['pms']['office_code']}\" isn't mapped to an HRIS office yet. Map it first, under Office mapping.");

        $officeChanged = (int) $inventory->office_id !== $mapping['office_id'];

        $inventory->update([
            'status' => Inventory::STATUS_ACTIVE,
            'status_changed_at' => now()->toDateString(),
            'status_note' => null,
            'office_id' => $mapping['office_id'],
            'office_code' => $mapping['office_code'],
            'office_name' => $mapping['office_name'],
            ...($officeChanged ? ['division_id' => null, 'division_name' => null] : []),
        ]);

        $logger->addContext($inventory, ['source' => 'PMS', 'pms_property_no' => $item['pms']['property_no'], 'pms_office' => $item['pms']['office_code']]);

        return response()->json(['message' => "{$inventory->property_number} is active again, at {$mapping['office_code']}."]);
    }

    /**
     * Matches an item that has no PMS match to a PMS property: either by
     * taking the PMS property number, or by linking them and keeping the
     * ITSMS number.
     */
    public function link(Request $request, Inventory $inventory, PmsReconciliation $reconciliation, ActivityLogger $logger)
    {
        $data = $request->validate([
            'pms_property_id' => ['required', 'integer', 'exists:pms_properties,id'],
            'use_pms_number' => ['required', 'boolean'],
        ]);

        $current = $reconciliation->pmsIdFor($inventory->id);

        if ($current !== null) {
            abort(422, "{$inventory->property_number} is already matched to PMS {$this->pmsNumber($current)}.");
        }

        $holder = $reconciliation->inventoryIdFor($data['pms_property_id']);
        $property = PmsProperty::findOrFail($data['pms_property_id']);

        if ($holder !== null) {
            abort(422, "PMS {$property->property_no} is already matched to ".($reconciliation->inventorySummaryFor($holder)['property_number'] ?? 'another item').'.');
        }

        $previousNumber = $inventory->property_number;

        if ($data['use_pms_number']) {
            $request->validate([
                'pms_property_id' => [function ($attribute, $value, $fail) use ($property, $inventory) {
                    (new AvailablePropertyNumber($inventory->id))->validate('property_number', $property->property_no, $fail);
                }],
            ]);

            $inventory->update(['property_number' => $property->property_no]);
        }

        // Also when the numbers now agree: a later PMS edit to the number
        // shouldn't undo a match someone confirmed.
        PmsInventoryLink::create([
            'inventory_id' => $inventory->id,
            'pms_property_id' => $property->id,
            'linked_by_profile_id' => Auth::user()?->profile?->id,
        ]);

        $logger->addContext($inventory, ['source' => 'PMS', 'pms_property_no' => $property->property_no]);

        return response()->json([
            'message' => $data['use_pms_number']
                ? "{$previousNumber} is now {$property->property_no}, matched to PMS."
                : "{$inventory->property_number} is matched to PMS {$property->property_no}.",
        ]);
    }

    /** Undoes a link made by hand. A number change made with it stays. */
    public function unlink(Inventory $inventory, ActivityLogger $logger)
    {
        $link = PmsInventoryLink::where('inventory_id', $inventory->id)->first();

        abort_unless($link, 422, "{$inventory->property_number} wasn't linked to PMS by hand.");

        $logger->addContext($inventory, ['source' => 'PMS', 'pms_property_no' => $this->pmsNumber($link->pms_property_id)]);
        $link->delete();

        return response()->json(['message' => "{$inventory->property_number} is no longer linked to PMS."]);
    }

    /** Keeps ITSMS as it is for one difference, until either side changes. */
    public function dismiss(Request $request, PmsReconciliation $reconciliation)
    {
        $data = $request->validate([
            'issue' => ['required', Rule::in(PmsReconciliation::ISSUES)],
            'inventory_id' => ['nullable', 'integer'],
            'pms_property_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $item = $reconciliation->find($data['issue'], $data['inventory_id'] ?? null, $data['pms_property_id'] ?? null);

        abort_unless($item, 422, 'That difference is no longer there. Refresh the list.');

        $dismissal = PmsReconciliationDismissal::updateOrCreate(
            [
                'issue' => $data['issue'],
                'inventory_id' => $data['inventory_id'] ?? null,
                'pms_property_id' => $data['pms_property_id'] ?? null,
            ],
            [
                'fingerprint' => $item['fingerprint'],
                'note' => $data['note'] ?? null,
                'dismissed_by_profile_id' => Auth::user()?->profile?->id,
            ],
        );

        return response()->json(['message' => 'Kept as it is.', 'data' => ['id' => $dismissal->id]]);
    }

    public function undismiss(PmsReconciliationDismissal $dismissal)
    {
        $dismissal->delete();

        return response()->json(['message' => 'Back on the list.']);
    }

    /** PMS properties by number, name or person, for linking an item by hand. */
    public function pmsProperties(Request $request, PmsReconciliation $reconciliation)
    {
        $data = $request->validate(['search' => ['required', 'string', 'min:2', 'max:255']]);
        $search = $data['search'];
        $key = PmsProperty::numberKey($search);

        $properties = PmsProperty::query()
            ->where(fn ($q) => $q
                ->where('property_no_key', 'like', "%{$key}%")
                ->orWhere('property_no_core', 'like', '%'.PmsProperty::numberCore($search).'%')
                ->orWhere('property_name', 'like', "%{$search}%")
                ->orWhere('full_name', 'like', "%{$search}%")
                ->orWhere('serial_number', 'like', "%{$key}%"))
            ->orderByRaw('property_no_key = ? desc', [$key])
            ->orderBy('property_no')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $properties->map(function (PmsProperty $property) use ($reconciliation) {
                $summary = $reconciliation->pmsSummary((object) $property->getAttributes());
                $holder = $summary['matched_inventory_id'];

                return [
                    ...$summary,
                    'matched_property_number' => $holder ? $reconciliation->inventorySummaryFor($holder)['property_number'] ?? null : null,
                ];
            }),
        ]);
    }

    private function pmsNumber(int $pmsPropertyId): string
    {
        return PmsProperty::whereKey($pmsPropertyId)->value('property_no') ?? "#{$pmsPropertyId}";
    }
}
