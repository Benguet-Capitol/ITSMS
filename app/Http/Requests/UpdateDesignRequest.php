<?php

namespace App\Http\Requests;

class UpdateDesignRequest extends StoreDesignRequest
{
    /**
     * Adds the release part of "For IT/MIS Office Use" and the requestor's
     * feedback on top of the create fields. Status, assigned personnel and
     * date received come from the workflow (RequestWorkflowController), so
     * they aren't editable here.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),

            'date_released' => ['nullable', 'date'],
            'received_by' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:5000'],

            'feedback_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'feedback_date' => ['nullable', 'date'],
        ];
    }
}
