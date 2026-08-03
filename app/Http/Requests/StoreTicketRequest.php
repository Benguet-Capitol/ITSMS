<?php

namespace App\Http\Requests;

use App\Enums\ServiceMethod;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'profile_id' => 'required|exists:profiles,id',
            'inventory_id' => 'nullable|exists:inventories,id',
            'it_service_id' => 'required|exists:it_services,id',
            'item_type_id' => 'required|exists:item_types,id',
            'agency_id' => 'nullable|exists:agencies,id',
            'concern' => 'required|string',
            'query_status' => ['required', new Enum(TicketStatus::class)],
            'request_status' => 'nullable|string',
            'priority' => 'nullable|string',
            'date' => 'nullable|date',
            'service_method' => ['required', new Enum(ServiceMethod::class)],
            'contact_number' => 'nullable|string',
            'is_other_agency' => 'boolean',
            'full_name' => 'nullable|string',
            'client_name' => 'nullable|string|max:255',

            'office_id' => 'nullable|string|max:255',
            'office_code' => 'nullable|string|max:255',
            'office_desc' => 'nullable|string|max:255',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $inventoryId = $this->input('inventory_id');
            $isOtherAgency = (bool) $this->input('is_other_agency');
            $officeId = $this->input('office_id');

            $closedStatuses = [
                TicketStatus::Resolved,
                TicketStatus::Assessed,
                TicketStatus::Cancelled,
            ];

            if ($inventoryId) {
                $hasOpenTicket = Ticket::where('inventory_id', $inventoryId)
                    ->whereNotIn('query_status', $closedStatuses)
                    ->exists();

                if ($hasOpenTicket) {
                    $validator->errors()->add(
                        'inventory',
                        'This inventory item already has an open ticket. Please close it before creating a new one.'
                    );
                }
            }

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
            'profile_id.required' => 'The :attribute is required.',
            'it_service_id.required' => 'The :attribute is required.',
            'item_type_id.required' => 'The :attribute is required.',
            'concern.required' => 'The :attribute is required.',
            'query_status.required' => 'The :attribute is required.',
            'date.required' => 'The :attribute is required.',
            'client_name.max' => 'The :attribute may not be greater than 255 characters.',
            'office_id.max' => 'The :attribute may not be greater than 255 characters.',
            'office_code.max' => 'The :attribute may not be greater than 255 characters.',
            'office_desc.max' => 'The :attribute may not be greater than 255 characters.',
        ];
    }
}
