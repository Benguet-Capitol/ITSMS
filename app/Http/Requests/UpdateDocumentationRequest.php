<?php

namespace App\Http\Requests;

class UpdateDocumentationRequest extends StoreDocumentationRequest
{
    /**
     * Adds "Action Taken" from the "For IT/MIS Office use" section and the
     * requestor's feedback on top of the create fields. Status, assigned
     * personnel and date received come from the workflow
     * (RequestWorkflowController), so they aren't editable here.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),

            'action_taken' => ['nullable', 'string', 'max:5000'],

            'feedback_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'feedback_date' => ['nullable', 'date'],
        ];
    }
}
