<?php

namespace App\Http\Requests;

use App\Models\NetworkAssistanceRequest;
use Illuminate\Validation\Rule;

class UpdateNetworkAssistanceRequest extends StoreNetworkAssistanceRequest
{
    /**
     * Adds the status, "For IT/MIS Office use" and requestor's feedback
     * sections on top of the create fields.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),

            'status' => ['required', 'string', Rule::in(NetworkAssistanceRequest::STATUSES)],

            'assigned_personnel' => ['nullable', 'string', 'max:255'],
            'date_received' => ['nullable', 'date'],
            'action_taken' => ['nullable', 'string', 'max:5000'],

            'feedback_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'feedback_name' => ['nullable', 'string', 'max:255'],
            'feedback_date' => ['nullable', 'date'],
        ];
    }
}
