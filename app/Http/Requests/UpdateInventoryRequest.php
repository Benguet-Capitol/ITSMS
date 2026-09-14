<?php

namespace App\Http\Requests;

use App\Models\ItemType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class UpdateInventoryRequest extends FormRequest
{
    private ?ItemType $resolvedItemTypeCache = null;

    private bool $itemTypeResolved = false;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * The item type for `item_type_id` (falling back to the inventory's
     * existing item type when omitted), resolved once and cached so
     * withValidator() and the controller don't each run the same query.
     */
    public function resolvedItemType(): ?ItemType
    {
        if (! $this->itemTypeResolved) {
            $itemTypeId = $this->input('item_type_id') ?? $this->route('inventory')?->item_type_id;
            $this->resolvedItemTypeCache = $itemTypeId ? ItemType::find($itemTypeId) : null;
            $this->itemTypeResolved = true;
        }

        return $this->resolvedItemTypeCache;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable'],
            'office_id' => ['nullable', 'integer'],
            'office_code' => ['nullable', 'string', 'max:255'],
            'office_name' => ['nullable', 'string', 'max:255'],
            'division_id' => ['nullable', 'integer'],
            'division_name' => ['nullable', 'string', 'max:255'],
            'item_type_id' => ['nullable', 'exists:item_types,id'],
            'brand_model_id' => ['nullable', 'exists:brand_models,id'],
            'parent_component_id' => ['nullable', 'exists:inventories,id'],

            'ip_address' => ['nullable', 'string', 'max:255'],
            'mac_address' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],

            'operating_system_name' => ['nullable', 'string', 'max:255'],
            'os_license_number' => ['nullable', 'string', 'max:255'],
            'anti_virus_name' => ['nullable', 'string', 'max:255'],
            'anti_virus_license_number' => ['nullable', 'string', 'max:255'],
            'microsoft_office_name' => ['nullable', 'string', 'max:255'],
            'ms_office_license_number' => ['nullable', 'string', 'max:255'],
            'other_installed_applications' => ['nullable', 'string', 'max:1000'],
            'property_number' => ['required', 'string', 'max:255', 'unique:inventories,property_number,'.$this->route('inventory')->id],
            'date_acquired' => ['nullable', 'date'],
            'warranty_expiration_date' => ['nullable', 'date'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],

            'internal_components' => 'array',
            'internal_components.*.brand_model.id' => 'required|integer',
            'internal_components.*.quantity' => 'required|integer|min:1',

            // 'parent_component' => ['nullable', 'string', 'max:255'],
            // 'inventory_type' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $parentComponentId = $this->input('parent_component_id');
            $internalComponents = $this->input('internal_components', []);

            $itemType = $this->resolvedItemType();

            if (! $itemType) {
                return;
            }

            $isMainInventory = (bool) $itemType->is_main_inventory;
            $isComponent = (bool) $itemType->is_component;
            $hasParent = ! empty($parentComponentId);
            $hasInternalComponents = ! empty($internalComponents);

            if ($hasParent && ! $isComponent) {
                $validator->errors()->add(
                    'parent_component_id',
                    "{$itemType->type} cannot be added as a component."
                );
            }

            if ($hasParent && $hasInternalComponents) {
                $validator->errors()->add(
                    'internal_components',
                    'Child components cannot contain internal components.'
                );
            }

            if (! $isMainInventory && $hasInternalComponents) {
                $validator->errors()->add(
                    'internal_components',
                    "{$itemType->type} cannot contain internal components."
                );
            }

            if ($hasParent && ! $this->filled('brand_model_id')) {
                $validator->errors()->add(
                    'brand_model_id',
                    'A component brand model is required.'
                );
            }
        });
    }
}
