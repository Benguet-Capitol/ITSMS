<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\HasWorkflowFields;
use App\Models\InspectionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionRequestResource extends JsonResource
{
    use HasWorkflowFields;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $overall = $this->overallResult();

        return [
            'id' => $this->id,
            'control_number' => $this->control_number,
            ...$this->workflowFields($request),

            'date_of_request' => $this->date_of_request?->format('Y-m-d'),
            'end_user_office' => $this->end_user_office,
            ...$this->officeResourceFields(),

            'procurement_mode' => $this->procurement_mode,
            'procurement_mode_label' => InspectionRequest::PROCUREMENT_MODES[$this->procurement_mode] ?? $this->procurement_mode,
            'po_number' => $this->po_number,
            'purchase_reference' => $this->purchaseReference(),
            'supplier' => $this->supplier,

            // Always included (the workflow actions only load personnel);
            // the list eager-loads them.
            'inspector_ids' => $this->inspectors->pluck('id')->all(),
            'inspectors' => InspectorResource::collection($this->inspectors),

            'items' => collect($this->items ?? [])
                ->map(fn (array $item) => [...$item, 'total' => InspectionRequest::lineTotal($item)])
                ->all(),
            'item_summaries' => $this->itemSummaries(),
            'total_amount' => $this->totalAmount(),

            'inspection_date' => $this->inspection_date?->format('Y-m-d'),
            'result' => $overall,
            'result_label' => $overall ? InspectionRequest::RESULTS[$overall] : null,
            'remarks' => $this->remarks,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
