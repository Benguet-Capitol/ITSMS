<?php

namespace App\Http\Requests;

use App\Models\IsChangeRequest;
use Illuminate\Validation\Rule;

class UpdateIsChangeRequest extends StoreIsChangeRequest
{
    /**
     * Adds the status, review, programmer action, testing/deployment,
     * requestor's feedback and user-acceptance sign-off sections on top of
     * the create fields.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),

            'status' => ['required', 'string', Rule::in(array_keys(IsChangeRequest::STATUSES))],

            'reviewed_by' => ['nullable', 'string', 'max:255'],
            'reviewed_date' => ['nullable', 'date'],

            'programmer_name' => ['nullable', 'string', 'max:255'],
            'date_received' => ['nullable', 'date'],

            'testing_schedule' => ['nullable', 'string', 'max:255'],
            'deployed_by' => ['nullable', 'string', 'max:255'],
            'approved_by' => ['nullable', 'string', 'max:255'],
            'approved_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:5000'],

            'feedback_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'feedback_name' => ['nullable', 'string', 'max:255'],
            'feedback_date' => ['nullable', 'date'],

            'accepted_by' => ['nullable', 'string', 'max:255'],
            'accepted_date' => ['nullable', 'date'],
        ];
    }
}
