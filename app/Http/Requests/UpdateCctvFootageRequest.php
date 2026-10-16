<?php

namespace App\Http\Requests;

class UpdateCctvFootageRequest extends StoreCctvFootageRequest
{
    /**
     * Adds the form's "Details to be completed by IT personnel" section and
     * the requestor's feedback on top of the create fields. Status comes
     * from the workflow (RequestWorkflowController); the IT section is
     * required when the request is marked for completion
     * (CctvFootageRequest::finishRequirements), not on every save.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),

            'extracted_at' => ['nullable', 'date_format:Y-m-d H:i'],
            'camera_name' => ['nullable', 'string', 'max:255'],
            'nvr_number' => ['nullable', 'string', 'max:50'],
            'file_names' => ['nullable', 'string', 'max:2000'],
            'extracted_by' => ['nullable', 'string', 'max:255'],
            'released_by' => ['nullable', 'string', 'max:255'],
            'received_by' => ['nullable', 'string', 'max:255'],

            'feedback_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'feedback_date' => ['nullable', 'date'],
        ];
    }
}
