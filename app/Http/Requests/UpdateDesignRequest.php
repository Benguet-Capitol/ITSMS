<?php

namespace App\Http\Requests;

use App\Models\DesignRequest;
use Illuminate\Validation\Rule;

class UpdateDesignRequest extends StoreDesignRequest
{
    /**
     * Adds the status, "For IT/MIS Office Use" (assignment and release)
     * and requestor's feedback sections on top of the create fields.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),

            'status' => ['required', 'string', Rule::in(DesignRequest::STATUSES)],

            'assigned_personnel' => ['nullable', 'string', 'max:255'],
            'date_received' => ['nullable', 'date'],
            'date_released' => ['nullable', 'date'],
            'received_by' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:5000'],

            'feedback_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'feedback_name' => ['nullable', 'string', 'max:255'],
            'feedback_date' => ['nullable', 'date'],
        ];
    }
}
