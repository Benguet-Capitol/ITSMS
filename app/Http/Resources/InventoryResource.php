<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\BrandModelResource;
use App\Http\Resources\ItemTypeResource;
use App\Http\Resources\InventoryInternalComponentResource;

class InventoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $inventory = $this?->loadMissing('internal_components');

        $internal_components = [];

        if ($inventory) {
            foreach ($inventory->internal_components as $internal_component) {
                $internal_components[] = [
                    'id' => $internal_component->id,
                    'brand_model' => $internal_component->brand_model,
                    'specific_serial_number' => $internal_component->specific_serial_number,
                    'slot' => $internal_component->slot,
                    'quantity' => $internal_component->quantity,
                    'notes' => $internal_component->notes,
                ];
            }
        }

        $parent_inventory = InventoryResource::make($this->whenLoaded('parent_component'));
        $item_type = ItemTypeResource::make($this->whenLoaded('item_type'));

        $computed_brand_model_search = $this->computed_brand_model_search;

        $employeeMap = $request->attributes->get('employeeMap');
        $employee = $employeeMap?->get((int) $this->employee_id);

        $employee_full_name = data_get($employee, 'fullname') ?: $this->employee_full_name;

        return [
            'id' => $this->id,

            'employee_id' => $this->employee_id,

            'employee' => $employee,
            'brand_model' => BrandModelResource::make($this->whenLoaded('brand_model')),

            'office_id' => $this->office_id,
            'office_code' => $this->office_code,
            'office_name' => $this->office_name,

            'division_id' => $this->division_id,
            'division_name' => $this->division_name,

            'item_type' => $item_type,
            'inventory' => $parent_inventory,

            'internal_components' => InventoryInternalComponentResource::collection($this->whenLoaded('internal_components')),
            'ip_address' => $this->ip_address,
            'mac_address' => $this->mac_address,
            'remarks' => $this->remarks,
            'operating_system_name' => $this->operating_system_name,
            'os_license_number' => $this->os_license_number,
            'anti_virus_name' => $this->anti_virus_name,
            'anti_virus_license_number' => $this->anti_virus_license_number,
            'microsoft_office_name' => $this->microsoft_office_name,
            'ms_office_license_number' => $this->ms_office_license_number,
            'other_installed_applications' => $this->other_installed_applications,
            'property_number' => $this->property_number,
            'date_acquired' => $this->date_acquired,
            'warranty_expiration_date' => $this->warranty_expiration_date,
            'serial_number' => $this->serial_number,
            'status' => $this->status,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'inventory_option_attribute' => "{$this->property_number} - {$computed_brand_model_search} ({$employee_full_name})",
        ];
    }
}
