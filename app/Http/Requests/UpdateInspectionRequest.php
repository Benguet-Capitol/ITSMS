<?php

namespace App\Http\Requests;

use App\Models\InspectionRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class UpdateInspectionRequest extends StoreInspectionRequest
{
    protected bool $recordsInspection = true;

    /**
     * Adds the inspection itself on top of the create fields: the date,
     * each item's result and remarks, and overall remarks. Status and
     * personnel come from the workflow (RequestWorkflowController).
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),

            'items.*.result' => ['nullable', 'string', Rule::in(array_keys(InspectionRequest::RESULTS))],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],

            'inspection_date' => ['nullable', 'date', 'after_or_equal:date_of_request'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * An inspector already on this request stays allowed after being set
     * inactive; only new picks must be active.
     */
    protected function inspectorExistsRule(): Exists
    {
        $current = $this->route('inspection_request')?->inspectors()->pluck('inspectors.id')->all() ?? [];

        return Rule::exists('inspectors', 'id')->where(
            fn ($q) => $q->where('is_active', true)->orWhereIn('id', $current)
        );
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'inspection_date.after_or_equal' => 'The inspection date cannot be before the date of request.',
        ];
    }
}
