<?php

namespace App\Http\Requests;

class UpdateDtsUserRequest extends StoreDtsUserRequest
{
    /**
     * Adds "Created by" / "Date Created" from the "For PGO-IT/MIS Use"
     * section and the requestor's feedback on top of the create fields.
     * Status and assigned personnel come from the workflow
     * (RequestWorkflowController), so they aren't editable here.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),

            'created_by' => ['nullable', 'string', 'max:255'],
            'date_created' => ['nullable', 'date'],

            'feedback_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'feedback_date' => ['nullable', 'date'],
        ];
    }
}
