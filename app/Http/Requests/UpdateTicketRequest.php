<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'inventory_id' => 'nullable|exists:inventories,id',
            'item_type_id' => ['required', 'exists:item_types,id'],
            'it_service_id' => 'required|exists:it_services,id',
            'agency_id' => 'nullable|exists:agencies,id',
            'office_id' => 'nullable|string|max:255',
            'office_code' => 'nullable|string|max:255',
            'office_desc' => 'nullable|string|max:255',
            'concern' => 'required|string',
            'priority' => 'nullable|string',
            'contact_number' => 'nullable|string',
            'is_other_agency' => 'boolean',
            'full_name' => 'nullable|string',
            'client_name' => 'nullable|string|max:255',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $inventoryId = $this->input('inventory_id');
            $isOtherAgency = filter_var($this->input('is_other_agency'), FILTER_VALIDATE_BOOLEAN);
            $officeId = $this->input('office_id');

            if (!$inventoryId && !$isOtherAgency && !$officeId) {
                $validator->errors()->add(
                    'office_id',
                    'Office is required when no inventory is selected.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'it_service_id.required' => 'The :attribute is required.',
            'item_type_id.required' => 'The :attribute is required.',
            'concern.required' => 'The :attribute is required.',
            'client_name.max' => 'The :attribute may not be greater than 255 characters.',
        ];
    }
}