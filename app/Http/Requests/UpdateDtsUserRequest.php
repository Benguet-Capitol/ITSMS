<?php

namespace App\Http\Requests;

use App\Models\DtsUserRequest;
use Illuminate\Validation\Rule;

class UpdateDtsUserRequest extends StoreDtsUserRequest
{
    /**
     * Adds the status, "For PGO-IT/MIS Use" and requestor's feedback
     * sections on top of the create fields.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),

            'status' => ['required', 'string', Rule::in(DtsUserRequest::STATUSES)],

            'assigned_personnel' => ['nullable', 'string', 'max:255'],
            'created_by' => ['nullable', 'string', 'max:255'],
            'date_created' => ['nullable', 'date'],

            'feedback_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'feedback_name' => ['nullable', 'string', 'max:255'],
            'feedback_date' => ['nullable', 'date'],
        ];
    }
}
