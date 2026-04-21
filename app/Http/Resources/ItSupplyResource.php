<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\BrandModelResource;
use App\Http\Resources\MeasurementUnitResource;

class ItSupplyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
          'id' => $this->id,
          'brand_model' => BrandModelResource::make($this->whenLoaded('brand_model')),
          'measurement_unit' => MeasurementUnitResource::make($this->whenLoaded('measurement_unit')),
          'description' => $this->brand_model->specification ? $this->brand_model->item_type->type . ', ' . $this->brand_model->specification . ', ' . $this->brand_model->brand->name . ' ' . $this->brand_model->name : $this->brand_model->item_type->type . ', ' .$this->brand_model->brand->name . ' ' . $this->brand_model->name,
          'item_number' => $this->item_number,
          'stock_number' => $this->stock_number,
          'ics_number' => $this->ics_number,
          'iar_number' => $this->iar_number,
          'po_number' => $this->po_number,
          'quantity' => $this->quantity,
          'created_at' => $this->created_at,
          'updated_at' => $this->updated_at,
        ];
    }
}
