<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

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
            'concern' => 'required|string',
            'priority' => 'nullable|string',
            'contact_number' => 'nullable|string',
            'is_other_agency' => 'boolean',
            'full_name' => 'nullable|string',
            'client_name' => 'nullable|string|max:255',
        ];
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