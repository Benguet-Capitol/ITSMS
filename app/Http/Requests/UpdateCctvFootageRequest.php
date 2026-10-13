<?php

namespace App\Http\Requests;

use App\Models\CctvFootageRequest;
use Closure;
use Illuminate\Validation\Rule;

class UpdateCctvFootageRequest extends StoreCctvFootageRequest
{
    /**
     * Adds the approval/release workflow on top of the create fields. The
     * status may only move along CctvFootageRequest::TRANSITIONS, and
     * releasing footage requires the IT extraction details the form's
     * "Details to be completed by IT personnel" section asks for.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        /** @var CctvFootageRequest $record */
        $record = $this->route('cctv_footage_request');

        $releasing = fn () => $this->input('status') === CctvFootageRequest::STATUS_RELEASED;

        return [
            ...parent::rules(),

            'status' => [
                'required',
                'string',
                Rule::in(CctvFootageRequest::STATUSES),
                function (string $attribute, mixed $value, Closure $fail) use ($record) {
                    if (is_string($value) && ! $record->canTransitionTo($value)) {
                        $fail("A {$record->status} request cannot be moved to {$value}.");
                    }
                },
            ],
            'denial_reason' => ['nullable', 'string', 'max:2000', 'required_if:status,denied'],

            'extracted_at' => ['nullable', 'date_format:Y-m-d H:i', Rule::requiredIf($releasing)],
            'camera_name' => ['nullable', 'string', 'max:255', Rule::requiredIf($releasing)],
            'nvr_number' => ['nullable', 'string', 'max:50'],
            'file_names' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(fn () => $releasing() && $this->input('purpose') === 'copy'),
            ],
            'extracted_by' => ['nullable', 'string', 'max:255', Rule::requiredIf($releasing)],
            'released_by' => ['nullable', 'string', 'max:255', Rule::requiredIf($releasing)],
            'received_by' => ['nullable', 'string', 'max:255', Rule::requiredIf($releasing)],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'denial_reason.required_if' => 'Please state the reason for denying the request.',
            'extracted_at.required' => 'The date and time of playback/extraction is required to release footage.',
            'camera_name.required' => 'The camera name is required to release footage.',
            'file_names.required' => 'The file name/s are required when a copy is released.',
            'extracted_by.required' => 'The IT personnel who extracted the footage is required.',
            'released_by.required' => 'The name of the person who released the footage is required.',
            'received_by.required' => 'The name of the person who viewed/received the footage is required.',
        ];
    }

    public function cctvAttributes(): array
    {
        $data = parent::cctvAttributes();

        if (($data['status'] ?? null) !== CctvFootageRequest::STATUS_DENIED) {
            $data['denial_reason'] = null;
        }

        return $data;
    }
}
