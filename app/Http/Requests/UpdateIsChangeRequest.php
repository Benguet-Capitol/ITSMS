<?php

namespace App\Http\Requests;

use App\Enums\RequestStatus;
use App\Models\IsChangeRequest;
use Closure;
use Illuminate\Validation\Rule;

class UpdateIsChangeRequest extends StoreIsChangeRequest
{
    /**
     * Adds the development step, review, testing/deployment, requestor's
     * feedback and user-acceptance sign-off sections on top of the create
     * fields. Status and the programmer's action (who, and the date the
     * request was received) come from the workflow
     * (RequestWorkflowController); the step may be set in any order, but
     * only while the request is in progress.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        /** @var IsChangeRequest $record */
        $record = $this->route('is_change_request');

        return [
            ...parent::rules(),

            'stage' => [
                'nullable',
                'string',
                Rule::in(array_keys(IsChangeRequest::STAGES)),
                function (string $attribute, mixed $value, Closure $fail) use ($record) {
                    if ($value !== $record->stage && $record->status !== RequestStatus::InProgress) {
                        $fail('The development step can only change while the request is In Progress.');
                    }
                },
            ],

            'reviewed_by' => ['nullable', 'string', 'max:255'],
            'reviewed_date' => ['nullable', 'date'],

            'testing_schedule' => ['nullable', 'string', 'max:255'],
            'deployed_by' => ['nullable', 'string', 'max:255'],
            'approved_by' => ['nullable', 'string', 'max:255'],
            'approved_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:5000'],

            'feedback_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'feedback_date' => ['nullable', 'date'],

            'accepted_by' => ['nullable', 'string', 'max:255'],
            'accepted_date' => ['nullable', 'date'],
        ];
    }
}
