<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Models\Concerns\HasRequesterOffice;
use App\Models\Concerns\HasRequestWorkflow;
use App\Models\Concerns\HasYearlyControlNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Inspection of a delivered IT purchase (docs/requests-inspection.md).
 * Follows the shared request workflow, but an inspection is an internal
 * check with no requestor's rating, so an admin completes it directly
 * once it's marked for completion.
 */
class InspectionRequest extends Model
{
    use HasRequesterOffice, HasRequestWorkflow, HasYearlyControlNumber;

    public const HAS_FEEDBACK = false;

    /** End User text column (HasRequesterOffice). */
    public const OFFICE_FIELD = 'end_user_office';

    /** Inspections are for the province's own purchases. */
    public const ALLOWS_OTHER_AGENCY = false;

    public const PROCUREMENT_MODES = [
        'purchase_order' => 'Purchase Order',
        'emergency' => 'Emergency Purchase',
        'direct_acquisition' => 'Direct Acquisition',
    ];

    /** Per-item inspection result, worst last (see overallResult()). */
    public const RESULTS = [
        'passed' => 'Passed',
        'partial' => 'Partial',
        'failed' => 'Failed',
    ];

    protected $fillable = [
        'control_number',
        'date_of_request',
        'end_user_office',
        'procurement_mode',
        'po_number',
        'supplier',
        'items',
        'inspection_date',
        'remarks',
    ];

    protected $attributes = [
        'status' => RequestStatus::Pending->value,
    ];

    protected $casts = [
        'date_of_request' => 'date:Y-m-d',
        'inspection_date' => 'date:Y-m-d',
        'items' => 'array',
    ];

    /**
     * The people who inspect the delivery (sign the report). Not
     * necessarily IT staff, unlike the workflow personnel.
     */
    public function inspectors(): BelongsToMany
    {
        return $this->belongsToMany(Inspector::class)->orderBy('name');
    }

    /**
     * The worst item result: failed if any item failed, partial if any was
     * partial, passed when all passed -- null until every item has one.
     */
    public function overallResult(): ?string
    {
        $results = collect($this->items ?? [])->pluck('result');

        if ($results->isEmpty() || $results->contains(null)) {
            return null;
        }

        foreach (array_reverse(array_keys(self::RESULTS)) as $result) {
            if ($results->contains($result)) {
                return $result;
            }
        }

        return null;
    }

    /** An item's quantity × unit cost; null when it has no unit cost. */
    public static function lineTotal(array $item): ?float
    {
        return isset($item['unit_cost'])
            ? round($item['quantity'] * $item['unit_cost'], 2)
            : null;
    }

    /** Sum of the items' line totals; null when no item has a unit cost. */
    public function totalAmount(): ?float
    {
        $totals = collect($this->items ?? [])
            ->map(fn (array $item) => self::lineTotal($item))
            ->filter(fn (?float $total) => $total !== null);

        return $totals->isEmpty() ? null : round($totals->sum(), 2);
    }

    /**
     * One readable line per item, e.g. "3 × Desktop Computer (Intel Core
     * Ultra 5) -- Partial".
     *
     * @return list<string>
     */
    public function itemSummaries(): array
    {
        return collect($this->items ?? [])
            ->map(function (array $item) {
                $line = "{$item['quantity']} × {$item['description']}";
                if (! empty($item['brand'])) {
                    $line .= " ({$item['brand']})";
                }
                if (! empty($item['result'])) {
                    $line .= ' -- '.self::RESULTS[$item['result']];
                }

                return $line;
            })
            ->values()
            ->all();
    }

    /** The PO number, or the procurement mode when there's no PO. */
    public function purchaseReference(): string
    {
        return $this->procurement_mode === 'purchase_order' && $this->po_number
            ? "PO {$this->po_number}"
            : (self::PROCUREMENT_MODES[$this->procurement_mode] ?? $this->procurement_mode);
    }

    /** The inspection must be recorded before it's marked for completion. */
    public function finishRequirements(): array
    {
        $missing = [];

        if (! $this->inspection_date) {
            $missing['inspection_date'] = 'Enter the inspection date before marking it for completion.';
        }

        if (collect($this->items ?? [])->contains(fn (array $item) => empty($item['result']))) {
            $missing['items'] = 'Record a result for every item before marking it for completion.';
        }

        return $missing;
    }
}
