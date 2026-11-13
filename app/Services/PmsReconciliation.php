<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\PmsInventoryLink;
use App\Models\PmsOfficeMapping;
use App\Models\PmsProperty;
use App\Models\PmsReconciliationDismissal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Compares live inventory with the last copy of PGSO's property list
 * (docs/inventory-reconciliation.md) and lists the differences. It only
 * reads; fixing a difference is a separate, deliberate action.
 *
 * Matching: a hand-made link first, then equal property numbers (upper
 * case, whitespace ignored). Numbers that agree only once prefixes like
 * "ICS " are removed are offered as suggestions, never matched silently.
 */
class PmsReconciliation
{
    /**
     * Shown in this order. "returned": active in ITSMS, returned in PMS.
     * "reissued": returned, disposed or donated in ITSMS, but PMS has it at
     * an office again (docs/inventory-status.md).
     */
    public const ISSUES = ['returned', 'reissued', 'office', 'serial', 'parent', 'not_in_pms', 'pms_only'];

    /** @var Collection<int, object> live inventory by id */
    private Collection $inventory;

    /** @var Collection<int, object> PMS properties by id */
    private Collection $pms;

    /** @var array<int, int> inventory id => PMS id */
    private array $inventoryToPms = [];

    /** @var array<int, int> PMS id => inventory id */
    private array $pmsToInventory = [];

    /** @var array<int, true> inventory ids matched by a hand-made link */
    private array $linked = [];

    private bool $loaded = false;

    /** Every difference, each as an array (see item()). */
    public function issues(): Collection
    {
        $this->load();

        $mappings = PmsOfficeMapping::all()->keyBy(fn ($m) => strtoupper($m->pms_office_code));
        $dismissals = PmsReconciliationDismissal::with('dismissedBy:id,display_name')->get()
            ->keyBy(fn ($d) => self::key($d->issue, $d->inventory_id, $d->pms_property_id));
        $returnedOffice = strtoupper((string) config('services.pms.returned_office'));

        $cores = $this->pms->groupBy('property_no_core');
        $items = collect();

        foreach ($this->inventory as $item) {
            $pmsId = $this->inventoryToPms[$item->id] ?? null;

            if ($pmsId === null) {
                $suggestion = $cores->get(PmsProperty::numberCore($item->property_number), collect())
                    ->first(fn ($p) => ! isset($this->pmsToInventory[$p->id]));

                $items->push($this->item('not_in_pms', $item, null, [$item->property_number], [
                    'suggestion' => $suggestion ? $this->pmsSummary($suggestion) : null,
                ]));

                continue;
            }

            $property = $this->pms[$pmsId];
            $inService = ($item->status ?? Inventory::STATUS_ACTIVE) === Inventory::STATUS_ACTIVE;
            $pmsReturned = strtoupper((string) $property->office_code) === $returnedOffice;
            $mapping = $property->office_code !== null ? $mappings->get(strtoupper($property->office_code)) : null;
            $mappingSummary = $mapping ? [
                'office_id' => $mapping->office_id,
                'office_code' => $mapping->office_code,
                'office_name' => $mapping->office_name,
            ] : null;

            if ($inService && $pmsReturned) {
                $items->push($this->item('returned', $item, $property, [$property->office_code]));
            } elseif (! $inService && ! $pmsReturned && $property->office_code !== null) {
                // PMS keeps returned, disposed and donated items under the
                // returned office, so an office means PGSO issued it again.
                $items->push($this->item('reissued', $item, $property, [$item->status, $property->office_code, $mapping?->office_id], [
                    'office_mapping' => $mappingSummary,
                ]));
            } elseif ($inService && $item->parent_component_id === null && $property->office_code !== null) {
                // Components take their office from the asset they're in, so only stand-alone items are compared.
                $sameOffice = $mapping
                    ? (int) $item->office_id === $mapping->office_id
                    : in_array(strtoupper($property->office_code), [strtoupper((string) $item->office_code), strtoupper((string) $item->office_name)], true);

                if (! $sameOffice) {
                    $items->push($this->item('office', $item, $property, [$item->office_id, $item->office_code, $property->office_code, $mapping?->office_id], [
                        'office_mapping' => $mappingSummary,
                    ]));
                }
            }

            if (filled($item->serial_number) && $property->serial_number !== null
                && ! str_contains(PmsProperty::numberKey($property->property_details), PmsProperty::numberKey($item->serial_number))) {
                $items->push($this->item('serial', $item, $property, [$item->serial_number, $property->property_details]));
            }

            // Only when PMS puts it under an asset that ITSMS also has, and
            // only for items in service.
            $expectedParent = $inService && $property->main_id ? ($this->pmsToInventory[$property->main_id] ?? null) : null;

            if ($expectedParent !== null && (int) $item->parent_component_id !== $expectedParent) {
                $items->push($this->item('parent', $item, $property, [$item->parent_component_id, $property->main_id], [
                    'expected_parent' => $this->inventorySummary($this->inventory[$expectedParent]),
                ]));
            }
        }

        foreach ($this->pms as $property) {
            if (! isset($this->pmsToInventory[$property->id]) && strtoupper((string) $property->office_code) !== $returnedOffice) {
                $items->push($this->item('pms_only', null, $property, [$property->property_no_key, $property->office_code]));
            }
        }

        return $items->map(function (array $item) use ($dismissals) {
            $dismissal = $dismissals->get(self::key($item['issue'], $item['inventory']['id'] ?? null, $item['pms']['id'] ?? null));

            // A dismissal only holds while the values it was made on are unchanged.
            $item['dismissal'] = $dismissal && $dismissal->fingerprint === $item['fingerprint'] ? [
                'id' => $dismissal->id,
                'note' => $dismissal->note,
                'dismissed_by' => $dismissal->dismissedBy?->display_name,
                'dismissed_at' => $dismissal->updated_at?->toIso8601String(),
            ] : null;

            return $item;
        });
    }

    /** The difference of this kind for an inventory item, if there is one. */
    public function issueFor(string $issue, int $inventoryId): ?array
    {
        return $this->issues()->first(fn ($item) => $item['issue'] === $issue && ($item['inventory']['id'] ?? null) === $inventoryId);
    }

    /** The difference for one subject, as dismissals name it. */
    public function find(string $issue, ?int $inventoryId, ?int $pmsPropertyId): ?array
    {
        $key = self::key($issue, $inventoryId, $pmsPropertyId);

        return $this->issues()->first(fn ($item) => $item['key'] === $key);
    }

    /**
     * One item against PMS, for its own page: its PMS property (or a
     * likely one), and its differences, kept ones included.
     *
     * @return array{status: string, pms: ?array, suggestion: ?array, differences: list<array>}
     */
    public function forInventory(int $inventoryId): array
    {
        $this->load();

        if ($this->pms->isEmpty()) {
            return ['status' => 'not_synced', 'pms' => null, 'suggestion' => null, 'differences' => []];
        }

        $differences = $this->issues()
            ->filter(fn (array $item) => ($item['inventory']['id'] ?? null) === $inventoryId)
            ->values();

        $pmsId = $this->inventoryToPms[$inventoryId] ?? null;
        $notInPms = $differences->firstWhere('issue', 'not_in_pms');

        return [
            'status' => $pmsId !== null ? 'matched' : 'not_found',
            'pms' => $pmsId !== null ? $this->pmsSummary($this->pms[$pmsId]) : null,
            'suggestion' => $notInPms['suggestion'] ?? null,
            // Not being in PMS is the status itself, not a row to fix.
            'differences' => $differences->reject(fn (array $item) => $item['issue'] === 'not_in_pms')->values()->all(),
        ];
    }

    /**
     * Whether each item is in PMS and how many differences it has open
     * (kept ones and "not in PMS" not counted), for the inventory list's
     * badges. Ids that aren't live items are left out; nothing at all
     * before the first sync.
     *
     * @param  list<int>  $inventoryIds
     * @return array<int, array{status: string, open: int}>
     */
    public function statuses(array $inventoryIds): array
    {
        $this->load();

        if ($this->pms->isEmpty()) {
            return [];
        }

        $wanted = array_flip($inventoryIds);

        $open = $this->issues()
            ->filter(fn (array $item) => $item['dismissal'] === null
                && $item['issue'] !== 'not_in_pms'
                && isset($wanted[$item['inventory']['id'] ?? -1]))
            ->countBy(fn (array $item) => $item['inventory']['id']);

        $statuses = [];

        foreach ($inventoryIds as $id) {
            if (isset($this->inventory[$id])) {
                $statuses[$id] = [
                    'status' => isset($this->inventoryToPms[$id]) ? 'matched' : 'not_found',
                    'open' => $open[$id] ?? 0,
                ];
            }
        }

        return $statuses;
    }

    /** The PMS property an inventory item is matched to, if any. */
    public function pmsIdFor(int $inventoryId): ?int
    {
        $this->load();

        return $this->inventoryToPms[$inventoryId] ?? null;
    }

    /** The inventory item a PMS property is matched to, if any. */
    public function inventoryIdFor(int $pmsPropertyId): ?int
    {
        $this->load();

        return $this->pmsToInventory[$pmsPropertyId] ?? null;
    }

    public function inventorySummaryFor(int $inventoryId): ?array
    {
        $this->load();

        return isset($this->inventory[$inventoryId]) ? $this->inventorySummary($this->inventory[$inventoryId]) : null;
    }

    /**
     * For each PMS office, the ITSMS office most of its matched items are
     * at -- a suggestion for the office mapping.
     *
     * @return array<string, array{office_id: int, office_code: ?string, office_name: ?string, items: int, of: int}>
     */
    public function officeSuggestions(): array
    {
        $this->load();

        $tally = [];

        foreach ($this->pmsToInventory as $pmsId => $inventoryId) {
            $item = $this->inventory[$inventoryId];
            $pmsOffice = $this->pms[$pmsId]->office_code;

            if ($pmsOffice === null || $item->parent_component_id !== null || $item->office_id === null) {
                continue;
            }

            $tally[strtoupper($pmsOffice)][$item->office_id][] = $item;
        }

        $suggestions = [];

        foreach ($tally as $pmsOffice => $byOffice) {
            uasort($byOffice, fn ($a, $b) => count($b) <=> count($a));
            $top = reset($byOffice);

            $suggestions[$pmsOffice] = [
                'office_id' => (int) $top[0]->office_id,
                'office_code' => $top[0]->office_code,
                'office_name' => $top[0]->office_name,
                'items' => count($top),
                'of' => array_sum(array_map('count', $byOffice)),
            ];
        }

        return $suggestions;
    }

    public function pmsSummary(object $property): array
    {
        $this->load();

        $parent = $property->main_id ? $this->pms->get($property->main_id) : null;

        return [
            'id' => (int) $property->id,
            'property_no' => $property->property_no,
            'property_name' => $property->property_name,
            'office_code' => $property->office_code,
            'full_name' => $property->full_name,
            'serial_number' => $property->serial_number,
            'property_details' => $property->property_details,
            'property_unit' => $property->property_unit,
            'property_price' => $property->property_price,
            'parent_property_no' => $parent?->property_no,
            'matched_inventory_id' => $this->pmsToInventory[$property->id] ?? null,
        ];
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->inventory = DB::table('inventories as i')
            ->leftJoin('item_types as t', 't.id', '=', 'i.item_type_id')
            ->whereNull('i.deleted_at')
            ->get([
                'i.id', 'i.property_number', 'i.serial_number', 'i.office_id', 'i.office_code',
                'i.office_name', 'i.parent_component_id', 'i.status', 't.type as item_type',
            ])
            ->keyBy('id');

        $this->pms = DB::table('pms_properties')
            ->orderBy('id')
            ->get([
                'id', 'main_id', 'property_name', 'property_no', 'property_no_key', 'property_no_core',
                'property_unit', 'property_price', 'property_details', 'serial_number', 'full_name', 'office_code',
            ])
            ->keyBy('id');

        foreach (PmsInventoryLink::all() as $link) {
            if (isset($this->inventory[$link->inventory_id], $this->pms[$link->pms_property_id])) {
                $this->match($link->inventory_id, $link->pms_property_id);
                $this->linked[$link->inventory_id] = true;
            }
        }

        // A number PMS uses twice matches its lowest property id.
        $byKey = [];
        foreach ($this->pms as $property) {
            $byKey[$property->property_no_key] ??= (int) $property->id;
        }

        foreach ($this->inventory as $item) {
            $pmsId = $byKey[PmsProperty::numberKey($item->property_number)] ?? null;

            if (! isset($this->inventoryToPms[$item->id]) && $pmsId !== null && ! isset($this->pmsToInventory[$pmsId])) {
                $this->match($item->id, $pmsId);
            }
        }

        $this->loaded = true;
    }

    private function match(int $inventoryId, int $pmsId): void
    {
        $this->inventoryToPms[$inventoryId] = $pmsId;
        $this->pmsToInventory[$pmsId] = $inventoryId;
    }

    private function item(string $issue, ?object $inventory, ?object $property, array $compared, array $extra = []): array
    {
        return [
            'key' => self::key($issue, $inventory?->id, $property?->id),
            'issue' => $issue,
            'inventory' => $inventory ? $this->inventorySummary($inventory) : null,
            'pms' => $property ? $this->pmsSummary($property) : null,
            'fingerprint' => md5(json_encode($compared)),
            ...$extra,
        ];
    }

    private function inventorySummary(object $item): array
    {
        $parent = $item->parent_component_id ? $this->inventory->get($item->parent_component_id) : null;

        return [
            'id' => (int) $item->id,
            'property_number' => $item->property_number,
            'item_type' => $item->item_type,
            'office_id' => $item->office_id !== null ? (int) $item->office_id : null,
            'office_code' => $item->office_code ?? $parent?->office_code,
            'office_name' => $item->office_name ?? $parent?->office_name,
            'serial_number' => $item->serial_number,
            'status' => $item->status ?? Inventory::STATUS_ACTIVE,
            'status_label' => Inventory::statusLabel($item->status),
            'parent_component_id' => $item->parent_component_id !== null ? (int) $item->parent_component_id : null,
            'parent_property_number' => $parent?->property_number,
            'linked' => isset($this->linked[$item->id]),
        ];
    }

    private static function key(string $issue, ?int $inventoryId, ?int $pmsPropertyId): string
    {
        return $issue.':'.($inventoryId ?? '-').':'.($pmsPropertyId ?? '-');
    }
}
