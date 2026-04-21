<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Enums\TicketStatus;
use App\Enums\ServiceMethod;
use Illuminate\Validation\Rules\Enum;

class StoreTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'profile_id' => 'required|exists:profiles,id',
            // 'employee_id' => 'required|exists:employees,id',
            'inventory_id' => 'nullable|exists:inventories,id',
            'it_service_id' => 'required|exists:it_services,id',
            'item_type_id' => 'nullable|exists:item_types,id',
            'agency_id' => 'nullable|exists:agencies,id',
            // 'ticket_number' => 'required|string|max:255|unique:it_services,ticket_number',
            'concern' => 'required|string',
            'query_status' => ['required', new Enum(TicketStatus::class)],
            'request_status' => 'nullable|string',
            'priority' => 'nullable|string',
            'date' => 'nullable|date',
            'service_method' => ['required', new Enum(ServiceMethod::class)],
            'contact_number' => 'nullable|string',
            'is_other_agency' => 'boolean',
            'full_name' => 'nullable|string',
        ];
    }

    public function messages(): array {
        return [
            'profile_id.required'   => 'The :attribute is required.',
            // 'employee_id.required'   => 'The :attribute is required.',
            'inventory_id.required'   => 'The :attribute is required.',
            'it_service_id.required'   => 'The :attribute is required.',
            'item_type_id.required'   => 'The :attribute is required.',
            'agency_id.required'   => 'The :attribute is required.',
            'ticket_number.required'   => 'The :attribute is required.',
            'concern.required'   => 'The :attribute is required.',
            'query_status.required'   => 'The :attribute is required.',
            'request_status.required'   => 'The :attribute is required.',
            'priority.required'   => 'The :attribute is required.',
            'date.required'   => 'The :attribute is required.',
        ];
    }
}
